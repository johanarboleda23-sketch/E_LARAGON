<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Services\AccountingPeriodService;
use App\Services\BankReconciliationService;
use App\Services\PaymentPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly AccountingPeriodService $periodService,
        private readonly BankReconciliationService $bankReconciliationService,
        private readonly PaymentPostingService $paymentPostingService,
    ) {}

    public function createForSale(Sale $sale): View
    {
        return $this->create($sale);
    }

    public function createForPurchase(Purchase $purchase): View
    {
        abort_if($purchase->trashed(), 404);

        return $this->create($purchase);
    }

    public function createForSupportDocument(SupportDocument $document): View
    {
        return $this->create($document);
    }

    /**
     * Registra un abono/pago sobre una venta, compra o documento soporte. El tipo de documento
     * viene fijo por ruta para no exponer la resolución polimórfica directamente al usuario.
     */
    public function storeForSale(Request $request, Sale $sale): RedirectResponse
    {
        return $this->store($request, $sale, 'total');
    }

    public function storeForPurchase(Request $request, Purchase $purchase): RedirectResponse
    {
        return $this->store($request, $purchase, 'total_pagar');
    }

    public function storeForSupportDocument(Request $request, SupportDocument $document): RedirectResponse
    {
        return $this->store($request, $document, 'total');
    }

    private function create(Sale|Purchase|SupportDocument $document): View
    {
        $isIncome = $document instanceof Sale;
        $totalAttribute = match (true) {
            $document instanceof Sale => 'total',
            $document instanceof Purchase => 'total_pagar',
            default => 'total',
        };
        $document->loadMissing('accountingVoucher.lines.account');
        if ($document instanceof SupportDocument) {
            $document->loadMissing('supplier');
        }

        $balance = $document->balanceDue((float) $document->getAttribute($totalAttribute));
        abort_if($balance <= 0, 409, 'Este documento no tiene saldo pendiente.');

        $counterPrefixes = $isIncome ? ['1305'] : ['2205'];
        $withholdingPrefixes = $isIncome ? ['1355'] : ['2365', '2367', '2368'];
        $counterAccounts = $this->accountsForPrefixes($counterPrefixes);
        $withholdingAccounts = $this->accountsForPrefixes($withholdingPrefixes);
        $defaultCounterAccountId = $document->accountingVoucher?->lines
            ->first(fn ($line): bool => ($isIncome ? $line->debit > 0 : $line->credit > 0)
                && collect($counterPrefixes)->contains(fn (string $prefix): bool => str_starts_with($line->account?->code ?? '', $prefix)))
            ?->chart_of_account_id
            ?? $counterAccounts->first()?->id;

        $party = match (true) {
            $document instanceof Sale => $document->customer_name,
            $document instanceof Purchase => $document->provider,
            default => $document->supplier?->name ?? 'Proveedor',
        };
        $documentNumber = match (true) {
            $document instanceof Sale, $document instanceof Purchase => $document->invoice_number,
            default => $document->consecutive,
        };
        $originalWithholding = match (true) {
            $document instanceof Sale => (float) $document->retention_total,
            $document instanceof Purchase => (float) $document->retefuente,
            default => (float) $document->retention_total,
        };
        $oldWithholdings = old('withholdings', [['chart_of_account_id' => '', 'amount' => '']]);

        return view('payments.create', [
            'document' => $document,
            'documentNumber' => $documentNumber,
            'documentTotal' => (float) $document->getAttribute($totalAttribute),
            'balance' => $balance,
            'party' => $party,
            'isIncome' => $isIncome,
            'voucherTypeLabel' => $isIncome ? 'Recibo de caja' : 'Egreso',
            'originalWithholding' => $originalWithholding,
            'paymentMethods' => PaymentMethod::query()
                ->with('account')
                ->whereNotNull('chart_of_account_id')
                ->whereHas('account', fn ($query) => $query->where('active', true)->where('allows_posting', true))
                ->orderBy('name')
                ->get(),
            'counterAccounts' => $counterAccounts,
            'withholdingAccounts' => $withholdingAccounts,
            'defaultCounterAccountId' => $defaultCounterAccountId,
            'oldWithholdings' => is_array($oldWithholdings) && $oldWithholdings !== []
                ? $oldWithholdings
                : [['chart_of_account_id' => '', 'amount' => '']],
            'storeUrl' => route(match (true) {
                $document instanceof Sale => 'sales.payments.store',
                $document instanceof Purchase => 'purchases.payments.store',
                default => 'support-documents.payments.store',
            }, $document),
            'backUrl' => route(match (true) {
                $document instanceof Sale => 'sales.show',
                $document instanceof Purchase => 'purchases.show',
                default => 'support-documents.show',
            }, $document),
        ]);
    }

    private function store(Request $request, Sale|Purchase|SupportDocument $document, string $totalAttribute): RedirectResponse
    {
        $withholdingRows = $request->input('withholdings', []);
        if (is_array($withholdingRows)) {
            $withholdingRows = array_values(array_filter(
                $withholdingRows,
                fn (mixed $row): bool => ! is_array($row)
                    || filled($row['chart_of_account_id'] ?? null)
                    || filled($row['amount'] ?? null),
            ));
            $request->merge(['withholdings' => $withholdingRows]);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id'))],
            'counter_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', (int) session('company_id'))],
            'withholdings' => ['nullable', 'array', 'max:5'],
            'withholdings.*' => ['array'],
            'withholdings.*.chart_of_account_id' => ['required', 'integer', 'distinct', Rule::exists('chart_of_accounts', 'id')->where('company_id', (int) session('company_id'))],
            'withholdings.*.amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $paymentMethod = PaymentMethod::query()->with('account')->findOrFail($data['payment_method_id']);
        $account = $paymentMethod->account;
        if (! $account || ! $account->active || ! $account->allows_posting) {
            return back()->withErrors([
                'payment_method_id' => 'La forma de pago debe tener asociada una cuenta PUC auxiliar activa.',
            ])->withInput();
        }
        if (! $paymentMethod->is_cash && blank($data['reference'] ?? null)) {
            return back()->withErrors([
                'reference' => 'Ingresa la referencia del banco o del comprobante del corresponsal para poder conciliar este pago.',
            ])->withInput();
        }
        $module = $document instanceof Sale ? 'income' : 'expenses';
        $this->periodService->assertWritable($module, $data['payment_date']);

        $counterPrefixes = $document instanceof Sale ? ['1305'] : ['2205'];
        $counterAccount = ChartOfAccount::query()
            ->whereKey($data['counter_account_id'])
            ->where('active', true)
            ->where('allows_posting', true)
            ->where(function ($query) use ($counterPrefixes): void {
                foreach ($counterPrefixes as $prefix) {
                    $query->orWhere('code', 'like', $prefix.'%');
                }
            })
            ->first();
        if (! $counterAccount) {
            return back()->withErrors([
                'counter_account_id' => 'Selecciona una cuenta auxiliar de cartera (1305) o proveedores (2205), según corresponda.',
            ])->withInput();
        }
        if ((int) $counterAccount->id === (int) $paymentMethod->chart_of_account_id) {
            return back()->withErrors([
                'counter_account_id' => 'La cuenta de contrapartida debe ser distinta de la cuenta donde se recibió o realizó el pago.',
            ])->withInput();
        }

        $withholdingPrefixes = $document instanceof Sale ? ['1355'] : ['2365', '2367', '2368'];
        $withholdings = collect($data['withholdings'] ?? [])->map(function (array $row) use ($withholdingPrefixes): array {
            $account = ChartOfAccount::query()
                ->whereKey($row['chart_of_account_id'])
                ->where('active', true)
                ->where('allows_posting', true)
                ->where(function ($query) use ($withholdingPrefixes): void {
                    foreach ($withholdingPrefixes as $prefix) {
                        $query->orWhere('code', 'like', $prefix.'%');
                    }
                })
                ->first();

            if (! $account) {
                throw ValidationException::withMessages([
                    'withholdings' => 'Selecciona cuentas auxiliares activas de retención que correspondan a este tipo de pago.',
                ]);
            }

            return ['account' => $account, 'amount' => round((float) $row['amount'], 2)];
        });
        $withholdingTotal = round((float) $withholdings->sum('amount'), 2);

        return DB::transaction(function () use ($data, $document, $totalAttribute, $paymentMethod, $counterAccount, $withholdings, $withholdingTotal): RedirectResponse {
            $lockedDocument = $document->newQuery()
                ->lockForUpdate()
                ->findOrFail($document->getKey());
            if ($lockedDocument instanceof Purchase && $lockedDocument->accountingVoucher()
                ->whereHas('lines', fn ($query) => $query
                    ->where('credit', '>', 0)
                    ->whereHas('account', fn ($account) => $account->where('code', 'like', '11%')))
                ->exists()) {
                return back()->withErrors([
                    'amount' => 'Esta compra ya tiene una salida de caja o banco contabilizada. Revisa el comprobante antes de registrar otro pago.',
                ])->withInput();
            }
            $total = (float) $lockedDocument->getAttribute($totalAttribute);
            $balance = $lockedDocument->balanceDue($total);
            $amount = round((float) $data['amount'], 2);
            $appliedAmount = round($amount + $withholdingTotal, 2);
            if ($appliedAmount > round($balance, 2)) {
                return back()->withErrors([
                    'amount' => 'El efectivo más las retenciones no puede superar el saldo pendiente ($'.number_format($balance, 2).').',
                ])->withInput();
            }

            $payment = $this->paymentPostingService->post(
                $lockedDocument,
                $paymentMethod,
                $counterAccount,
                $amount,
                $withholdingTotal,
                $withholdings->all(),
                $data['payment_date'],
                trim($data['reference'] ?? '') ?: null,
                $data['notes'] ?? null,
            );
            $voucher = $payment->accountingVoucher;
            $matched = ! $paymentMethod->is_cash && $this->bankReconciliationService->matchPayment($payment->fresh());
            $message = 'Pago registrado con comprobante '.$voucher->consecutive.'.';
            $message .= $matched
                ? ' El movimiento bancario con referencia exacta quedó conciliado automáticamente.'
                : ' El comprobante quedó pendiente de conciliación bancaria.';

            return redirect()->route('accounting.vouchers.accounting', $voucher)->with('success', $message);
        });
    }

    /**
     * @param  array<int, string>  $prefixes
     * @return Collection<int, ChartOfAccount>
     */
    private function accountsForPrefixes(array $prefixes): Collection
    {
        return ChartOfAccount::query()
            ->where('active', true)
            ->where('allows_posting', true)
            ->where(function ($query) use ($prefixes): void {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('code', 'like', $prefix.'%');
                }
            })
            ->orderBy('code')
            ->get();
    }
}
