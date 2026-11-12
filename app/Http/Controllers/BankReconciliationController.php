<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucherLine;
use App\Models\BankOpeningBalance;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use App\Services\AccountingEntryService;
use App\Services\AccountingPeriodService;
use App\Services\BankReconciliationService;
use App\Support\SpreadsheetReader;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly AccountingPeriodService $periodService,
        private readonly AccountingEntryService $accountingEntryService,
        private readonly BankReconciliationService $reconciliationService,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->value();
        $paymentMethodId = $request->integer('payment_method_id') ?: null;
        $statements = BankStatement::query()
            ->when(in_array($status, ['pending', 'reconciled'], true), fn ($query) => $query->where('status', $status))
            ->when($paymentMethodId, fn ($query) => $query->where('payment_method_id', $paymentMethodId))
            ->with(['paymentMethod.account', 'accountingVoucher'])
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $summary = [
            'pending' => BankStatement::query()->when($paymentMethodId, fn ($query) => $query->where('payment_method_id', $paymentMethodId))->where('status', 'pending')->sum('amount'),
            'reconciled' => BankStatement::query()->when($paymentMethodId, fn ($query) => $query->where('payment_method_id', $paymentMethodId))->where('status', 'reconciled')->sum('amount'),
            'count' => BankStatement::query()->when($paymentMethodId, fn ($query) => $query->where('payment_method_id', $paymentMethodId))->count(),
        ];
        $bankAccounts = $this->bankAccounts();
        $postingAccounts = ChartOfAccount::query()->where('active', true)->where('allows_posting', true)->orderBy('code')->get();
        $thirdParties = ThirdParty::query()->where('active', true)->orderBy('name')->get();
        $unassignedCount = BankStatement::query()->whereNull('payment_method_id')->count();

        return view('bank-reconciliation.index', [
            'section' => 'movements',
            'statements' => $statements,
            'status' => $status,
            'summary' => $summary,
            'bankAccounts' => $bankAccounts,
            'postingAccounts' => $postingAccounts,
            'thirdParties' => $thirdParties,
            'paymentMethodId' => $paymentMethodId,
            'unassignedCount' => $unassignedCount,
        ]);
    }

    public function balances(Request $request): View
    {
        $bankAccounts = $this->bankAccounts();
        $data = $request->validate([
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id'))],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $selectedAccount = isset($data['payment_method_id'])
            ? $bankAccounts->firstWhere('id', (int) $data['payment_method_id'])
            : $bankAccounts->first();
        $date = $data['date'] ?? now()->toDateString();
        $openingBalance = null;
        $balance = null;

        if ($selectedAccount) {
            $openingBalance = BankOpeningBalance::query()
                ->where('payment_method_id', $selectedAccount->id)
                ->first();

            if ($openingBalance && $date >= $openingBalance->effective_date->toDateString()) {
                $statementTotals = BankStatement::query()
                    ->where('payment_method_id', $selectedAccount->id)
                    ->whereDate('transaction_date', '>=', $openingBalance->effective_date->toDateString())
                    ->whereDate('transaction_date', '<=', $date)
                    ->selectRaw("SUM(CASE WHEN transaction_type = 'credit' THEN amount ELSE 0 END) as credits")
                    ->selectRaw("SUM(CASE WHEN transaction_type = 'debit' THEN amount ELSE 0 END) as debits")
                    ->first();
                $bankBalance = round((float) $openingBalance->opening_balance + (float) $statementTotals->credits - (float) $statementTotals->debits, 2);

                $ledgerTotals = $selectedAccount->chart_of_account_id
                    ? AccountingVoucherLine::query()
                        ->where('chart_of_account_id', $selectedAccount->chart_of_account_id)
                        ->whereHas('voucher', fn ($query) => $query
                            ->where('voucher_type', '<>', 'saldos_iniciales')
                            ->whereDate('voucher_date', '>=', $openingBalance->effective_date->toDateString())
                            ->whereDate('voucher_date', '<=', $date))
                        ->selectRaw('SUM(debit) as debits, SUM(credit) as credits')
                        ->first()
                    : null;
                $ledgerBalance = $ledgerTotals
                    ? round((float) $openingBalance->opening_balance
                        + ((float) $ledgerTotals->debits - (float) $ledgerTotals->credits), 2)
                    : null;

                if ($selectedAccount->account?->nature === 'credit' && $ledgerTotals) {
                    $ledgerBalance = round((float) $openingBalance->opening_balance
                        + ((float) $ledgerTotals->credits - (float) $ledgerTotals->debits), 2);
                }

                $balance = [
                    'bank' => $bankBalance,
                    'ledger' => $ledgerBalance,
                    'difference' => $ledgerBalance === null ? null : round($bankBalance - $ledgerBalance, 2),
                    'credits' => (float) $statementTotals->credits,
                    'debits' => (float) $statementTotals->debits,
                    'ledger_configured' => $ledgerTotals !== null,
                ];
            }
        }

        $openingBalances = BankOpeningBalance::query()->with('paymentMethod')->get()->keyBy('payment_method_id');

        return view('bank-reconciliation.index', [
            'section' => 'balances',
            'bankAccounts' => $bankAccounts,
            'selectedAccount' => $selectedAccount,
            'date' => $date,
            'openingBalance' => $openingBalance,
            'balance' => $balance,
            'openingBalances' => $openingBalances,
            'status' => null,
            'paymentMethodId' => $selectedAccount?->id,
            'unassignedCount' => 0,
            'summary' => ['pending' => 0, 'reconciled' => 0, 'count' => 0],
        ]);
    }

    public function storeOpeningBalance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id'))],
            'effective_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'opening_balance' => ['required', 'numeric', 'between:-999999999999.99,999999999999.99'],
        ]);
        $paymentMethod = $this->bankAccountOrFail((int) $data['payment_method_id']);
        $existing = BankOpeningBalance::query()->where('payment_method_id', $paymentMethod->id)->first();
        if ($existing) {
            $this->periodService->assertWritable('bank', $existing->effective_date->toDateString());
        }
        $this->periodService->assertWritable('bank', $data['effective_date']);

        BankOpeningBalance::query()->updateOrCreate(
            [
                'company_id' => (int) session('company_id'),
                'payment_method_id' => $paymentMethod->id,
            ],
            [
                'effective_date' => $data['effective_date'],
                'opening_balance' => $data['opening_balance'],
                'updated_by' => auth()->id(),
                'created_by' => $existing?->created_by ?? auth()->id(),
            ],
        );

        return redirect()->route('bank-reconciliation.balances', [
            'payment_method_id' => $paymentMethod->id,
            'date' => $data['effective_date'],
        ])->with('success', 'Saldo inicial del banco guardado.');
    }

    public function assignAccount(Request $request, BankStatement $statement): RedirectResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id'))],
        ]);
        $this->periodService->assertWritable('bank', $statement->transaction_date->toDateString());
        $this->bankAccountOrFail((int) $data['payment_method_id']);
        $statement->update(['payment_method_id' => $data['payment_method_id']]);

        return back()->with('success', 'Movimiento asociado a la cuenta bancaria.');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id'))],
        ]);
        $paymentMethod = $this->bankAccountOrFail((int) $data['payment_method_id']);

        $movements = [];

        foreach (SpreadsheetReader::rows($data['file']->getRealPath(), $data['file']->getClientOriginalExtension()) as $row) {
            if (count(array_filter($row)) === 0) {
                continue;
            }

            if ($this->isHeaderRow($row)) {
                continue;
            }

            $compactFormat = count($row) <= 3;
            $bankExportFormat = count($row) === 4;
            $date = $this->parseDate($row[0] ?? '');
            $description = trim((string) ($compactFormat || $bankExportFormat ? ($row[1] ?? '') : ($row[2] ?? '')));
            $legacyType = strtolower(trim((string) ($compactFormat || $bankExportFormat ? '' : ($row[3] ?? ''))));
            $rawAmount = trim((string) ($compactFormat ? ($row[2] ?? '0') : ($bankExportFormat ? ($row[3] ?? '0') : ($row[4] ?? '0'))));
            $amount = $this->parseAmount($rawAmount);

            if (! $date) {
                continue;
            }

            $type = $legacyType !== ''
                ? $legacyType
                : ($amount < 0 ? 'debit' : 'credit');

            if (! in_array($type, ['credit', 'debit', 'ingreso', 'egreso'], true) || $amount == 0) {
                continue;
            }

            $transactionType = in_array($type, ['credit', 'ingreso'], true) ? 'credit' : 'debit';
            if ((float) $rawAmount < 0) {
                $transactionType = 'debit';
            }

            $this->periodService->assertWritable('bank', $date);
            $movements[] = [
                'company_id' => (int) session('company_id'),
                'payment_method_id' => $paymentMethod->id,
                'transaction_date' => $date,
                'reference' => $compactFormat ? null : (trim((string) ($bankExportFormat ? ($row[2] ?? '') : ($row[1] ?? ''))) ?: null),
                'description' => $description ?: null,
                'transaction_type' => $transactionType,
                'amount' => abs($amount),
                'status' => 'pending',
                'created_by' => auth()->id(),
            ];
        }

        DB::transaction(function () use ($movements): void {
            foreach ($movements as $movement) {
                $statement = BankStatement::create($movement);
                $this->reconciliationService->matchStatement($statement);
            }
        });
        $created = count($movements);

        return back()->with('success', "Se importaron {$created} movimientos bancarios.");
    }

    private function isHeaderRow(array $row): bool
    {
        $normalized = strtolower(implode('|', array_map('trim', $row)));

        return str_contains($normalized, 'fecha') && (str_contains($normalized, 'valor') || str_contains($normalized, 'importe'));
    }

    private function parseDate(mixed $value): ?string
    {
        if (is_numeric($value)) {
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseAmount(string $value): float
    {
        $normalized = trim(str_replace(['$', ' '], '', $value));
        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return (float) $normalized;
    }

    public function reconcile(Request $request, BankStatement $statement): RedirectResponse
    {
        $this->periodService->assertWritable('bank', $statement->transaction_date->toDateString());
        $data = $request->validate([
            'matched_type' => ['required', 'string', 'in:payment,manual'],
            'matched_id' => ['nullable', 'integer'],
            'third_party_id' => ['nullable', 'integer', Rule::exists('third_parties', 'id')->where('company_id', (int) session('company_id'))],
            'counterparts' => ['required_if:matched_type,manual', 'array', 'min:1'],
            'counterparts.*.chart_of_account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where('company_id', (int) session('company_id'))],
            'counterparts.*.amount' => ['nullable', 'numeric', 'gt:0'],
            'counterparts.*.detail' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['matched_type'] === 'payment') {
            $payment = Payment::query()->findOrFail($data['matched_id'] ?? 0);
            $matched = $this->reconciliationService->matchPayment($payment, $statement->id);
            if (! $matched) {
                return back()->withErrors([
                    'matched_id' => 'No existe una coincidencia única con la misma referencia, cuenta, tipo e importe.',
                ]);
            }

            return back()->with('success', 'Pago conciliado con el movimiento bancario.');
        }

        $counterparts = collect($data['counterparts'] ?? [])
            ->filter(fn (array $line): bool => filled($line['chart_of_account_id'] ?? null) || filled($line['amount'] ?? null))
            ->values();
        if ($counterparts->isEmpty() || $counterparts->contains(fn (array $line): bool => blank($line['chart_of_account_id'] ?? null) || blank($line['amount'] ?? null))) {
            return back()->withErrors([
                'counterparts' => 'Completa la cuenta y el importe de cada contrapartida.',
            ])->withInput();
        }

        return DB::transaction(function () use ($data, $statement, $counterparts): RedirectResponse {
            $lockedStatement = BankStatement::query()->lockForUpdate()->findOrFail($statement->id);
            abort_if($lockedStatement->status !== 'pending', 409, 'El movimiento ya fue conciliado.');
            $bankAccount = $lockedStatement->paymentMethod;
            $bankPucAccount = $bankAccount?->account;
            abort_unless($bankPucAccount && $bankPucAccount->active && $bankPucAccount->allows_posting, 422, 'Asocia al movimiento una cuenta bancaria con cuenta PUC auxiliar activa.');
            abort_unless(str_starts_with($bankPucAccount->code, '11'), 422, 'La cuenta seleccionada no corresponde a caja o bancos.');

            $amountTotal = round((float) $counterparts->sum(fn (array $line): float => (float) $line['amount']), 2);
            abort_if($amountTotal !== round((float) $lockedStatement->amount, 2), 422, 'Las contrapartidas deben sumar exactamente el importe del movimiento bancario.');

            $counterpartIds = $counterparts->pluck('chart_of_account_id')->map(fn ($id): int => (int) $id)->unique();
            abort_if($counterpartIds->contains((int) $bankPucAccount->id), 422, 'La cuenta de contrapartida debe ser distinta de la cuenta bancaria.');
            $accounts = ChartOfAccount::query()
                ->whereIn('id', $counterpartIds)
                ->where('active', true)
                ->where('allows_posting', true)
                ->get()
                ->keyBy('id');
            abort_if($accounts->count() !== $counterpartIds->count(), 422, 'Selecciona cuentas PUC auxiliares activas.');

            $thirdParty = isset($data['third_party_id'])
                ? ThirdParty::query()->findOrFail($data['third_party_id'])
                : null;
            $isCredit = $lockedStatement->transaction_type === 'credit';
            $lines = collect([[
                'chart_of_account_id' => $bankPucAccount->id,
                'detail' => 'Movimiento bancario '.$lockedStatement->reference,
                'debit' => $isCredit ? (float) $lockedStatement->amount : 0,
                'credit' => $isCredit ? 0 : (float) $lockedStatement->amount,
            ]]);
            foreach ($counterparts as $line) {
                $lines->push([
                    'chart_of_account_id' => (int) $line['chart_of_account_id'],
                    'detail' => $line['detail'] ?? $lockedStatement->description ?? 'Contrapartida conciliación',
                    'debit' => $isCredit ? 0 : (float) $line['amount'],
                    'credit' => $isCredit ? (float) $line['amount'] : 0,
                ]);
            }

            $voucher = $this->accountingEntryService->post([
                'voucher_type' => $isCredit ? 'recibo_caja' : 'egreso',
                'consecutive' => ($isCredit ? 'RC-BNK-' : 'EG-BNK-').$lockedStatement->id,
                'voucher_date' => $lockedStatement->transaction_date->toDateString(),
                'third_party' => $thirdParty?->name,
                'description' => 'Conciliación manual: '.($lockedStatement->description ?: $lockedStatement->reference),
            ], $lines);

            $lockedStatement->update([
                'status' => 'reconciled',
                'matched_type' => 'manual',
                'matched_id' => $voucher->id,
                'accounting_voucher_id' => $voucher->id,
                'notes' => $data['notes'] ?? null,
                'reconciled_at' => now(),
                'reconciled_by' => auth()->id(),
            ]);

            return back()->with('success', 'Movimiento conciliado y comprobante '.$voucher->consecutive.' creado.');
        });

        return back()->with('success', 'Movimiento conciliado correctamente.');
    }

    public function unmatch(BankStatement $statement): RedirectResponse
    {
        $this->periodService->assertWritable('bank', $statement->transaction_date->toDateString());
        abort_if($statement->matched_type === 'manual' && $statement->accounting_voucher_id, 409, 'El comprobante contable manual ya está publicado y debe conservarse. Registra un ajuste en lugar de desconciliarlo.');
        $statement->update([
            'status' => 'pending',
            'matched_type' => null,
            'matched_id' => null,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ]);

        return back()->with('success', 'Movimiento devuelto a pendientes.');
    }

    public function suggestions(BankStatement $statement): JsonResponse
    {
        $dateFrom = $statement->transaction_date->copy()->subDays(30);
        $dateTo = $statement->transaction_date->copy()->addDays(30);

        $payableTypes = $statement->transaction_type === 'credit'
            ? [Sale::class]
            : [Purchase::class, SupportDocument::class];
        $payments = $statement->reference ? Payment::query()
            ->with('payable')
            ->where('payment_method_id', $statement->payment_method_id)
            ->where('reference', $statement->reference)
            ->where('amount', $statement->amount)
            ->whereIn('payable_type', $payableTypes)
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => (float) $payment->amount,
                'third_party' => match (true) {
                    $payment->payable instanceof Sale => $payment->payable->customer_name,
                    $payment->payable instanceof Purchase => $payment->payable->provider,
                    $payment->payable instanceof SupportDocument => $payment->payable->supplier?->name,
                    default => null,
                },
            ]) : collect();

        return response()->json([
            'payments' => $payments,
            'sales' => Sale::query()
                ->where('total', $statement->amount)
                ->whereDate('sale_date', '>=', $dateFrom->toDateString())
                ->whereDate('sale_date', '<=', $dateTo->toDateString())
                ->latest('id')
                ->limit(5)
                ->get(['id', 'invoice_number', 'customer_name', 'sale_date', 'total'])
                ->all(),
            'purchases' => Purchase::query()
                ->where('total_pagar', $statement->amount)
                ->whereDate('purchase_date', '>=', $dateFrom->toDateString())
                ->whereDate('purchase_date', '<=', $dateTo->toDateString())
                ->latest('id')
                ->limit(5)
                ->get(['id', 'invoice_number', 'provider', 'purchase_date', 'total_pagar'])
                ->all(),
        ]);
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    private function bankAccounts(): Collection
    {
        return PaymentMethod::query()
            ->with('account')
            ->where('is_cash', false)
            ->where(function ($query): void {
                $query->where(function ($bankQuery): void {
                    $bankQuery->whereNotNull('bank_name')->where('bank_name', '<>', '');
                })->orWhere(function ($numberQuery): void {
                    $numberQuery->whereNotNull('account_number')->where('account_number', '<>', '');
                })
                    ->orWhere('name', 'like', '%Banco%')
                    ->orWhereHas('account', fn ($accountQuery) => $accountQuery->where('code', 'like', '1110%'));
            })
            ->orderBy('name')
            ->get();
    }

    private function bankAccountOrFail(int $paymentMethodId): PaymentMethod
    {
        $paymentMethod = $this->bankAccounts()->firstWhere('id', $paymentMethodId);
        abort_unless($paymentMethod, 422, 'Selecciona un medio de pago configurado como cuenta bancaria.');

        return $paymentMethod;
    }
}
