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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(
        private readonly AccountingPeriodService $periodService,
        private readonly BankReconciliationService $bankReconciliationService,
        private readonly PaymentPostingService $paymentPostingService,
    ) {}

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

    private function store(Request $request, Sale|Purchase|SupportDocument $document, string $totalAttribute): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id'))],
            'counter_account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', (int) session('company_id'))],
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

        return DB::transaction(function () use ($data, $document, $totalAttribute, $paymentMethod): RedirectResponse {
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
            if ($amount > round($balance, 2)) {
                return back()->withErrors([
                    'amount' => 'El abono no puede superar el saldo pendiente ($'.number_format($balance, 2).').',
                ])->withInput();
            }

            $counterAccount = ChartOfAccount::query()
                ->whereKey($data['counter_account_id'])
                ->where('active', true)
                ->where('allows_posting', true)
                ->first();
            if (! $counterAccount) {
                return back()->withErrors([
                    'counter_account_id' => 'Selecciona una cuenta PUC auxiliar activa.',
                ])->withInput();
            }

            if ((int) $counterAccount->id === (int) $paymentMethod->chart_of_account_id) {
                return back()->withErrors([
                    'counter_account_id' => 'La cuenta de contrapartida debe ser distinta de la cuenta donde se recibió o realizó el pago.',
                ])->withInput();
            }

            $payment = $this->paymentPostingService->post(
                $lockedDocument,
                $paymentMethod,
                $counterAccount,
                $amount,
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
}
