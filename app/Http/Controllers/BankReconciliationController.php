<?php

namespace App\Http\Controllers;

use App\Models\AccountingVoucherLine;
use App\Models\BankJournal;
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
use App\Services\PaymentPostingService;
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
        private readonly PaymentPostingService $paymentPostingService,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->value();
        $paymentMethodId = $request->integer('payment_method_id') ?: null;
        $statements = BankStatement::query()
            ->when(in_array($status, ['pending', 'reconciled'], true), fn ($query) => $query->where('status', $status))
            ->when($paymentMethodId, fn ($query) => $query->where('payment_method_id', $paymentMethodId))
            ->with(['paymentMethod.account', 'paymentMethod.bankJournal', 'accountingVoucher'])
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
        $bankJournals = BankJournal::query()
            ->with(['paymentMethod.account'])
            ->where('active', true)
            ->orderBy('name')
            ->get();
        $postingAccounts = ChartOfAccount::query()->where('active', true)->where('allows_posting', true)->orderBy('code')->get();
        $thirdParties = ThirdParty::query()->where('active', true)->orderBy('name')->get();
        $unassignedCount = BankStatement::query()->whereNull('payment_method_id')->count();

        return view('bank-reconciliation.index', [
            'section' => 'movements',
            'statements' => $statements,
            'status' => $status,
            'summary' => $summary,
            'bankAccounts' => $bankAccounts,
            'bankJournals' => $bankJournals,
            'postingAccounts' => $postingAccounts,
            'thirdParties' => $thirdParties,
            'paymentMethodId' => $paymentMethodId,
            'unassignedCount' => $unassignedCount,
        ]);
    }

    public function journals(): View
    {
        return view('bank-reconciliation.index', [
            'section' => 'journals',
            'bankAccounts' => $this->bankAccounts(),
            'bankJournals' => BankJournal::query()
                ->with(['paymentMethod.account'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeJournal(Request $request): RedirectResponse
    {
        $companyId = (int) session('company_id');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('bank_journals', 'name')->where('company_id', $companyId)],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')->where('company_id', $companyId),
                Rule::unique('bank_journals', 'payment_method_id')->where('company_id', $companyId),
            ],
        ]);
        $paymentMethod = $this->bankAccountOrFail((int) $data['payment_method_id']);
        $bankAccount = $paymentMethod->account;
        if (! $bankAccount || ! $bankAccount->active || ! $bankAccount->allows_posting || ! str_starts_with($bankAccount->code, '11')) {
            return back()->withErrors([
                'payment_method_id' => 'El diario debe estar asociado a una cuenta PUC auxiliar activa del grupo 11.',
            ])->withInput();
        }

        BankJournal::create([
            'company_id' => $companyId,
            'name' => trim($data['name']),
            'payment_method_id' => $paymentMethod->id,
            'active' => true,
        ]);

        return redirect()->route('bank-reconciliation.journals')->with('success', 'Diario bancario creado.');
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
            'journal_id' => [
                'required_without:payment_method_id',
                'nullable',
                'integer',
                Rule::exists('bank_journals', 'id')->where('company_id', (int) session('company_id'))->where('active', true),
            ],
            'payment_method_id' => [
                'required_without:journal_id',
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id')),
            ],
        ]);
        $this->periodService->assertWritable('bank', $statement->transaction_date->toDateString());
        if (isset($data['journal_id'])) {
            $journal = BankJournal::query()->with('paymentMethod.account')->findOrFail($data['journal_id']);
            $this->assertJournalBankAccount($journal->paymentMethod);
            $paymentMethodId = $journal->payment_method_id;
        } else {
            $paymentMethodId = $data['payment_method_id'];
            $this->bankAccountOrFail((int) $paymentMethodId);
        }
        $statement->update(['payment_method_id' => $paymentMethodId]);

        return back()->with('success', 'Movimiento asociado al diario bancario.');
    }

    public function import(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
            'journal_id' => [
                'required_without:payment_method_id',
                'nullable',
                'integer',
                Rule::exists('bank_journals', 'id')->where('company_id', (int) session('company_id'))->where('active', true),
            ],
            'payment_method_id' => [
                'required_without:journal_id',
                'nullable',
                'integer',
                Rule::exists('payment_methods', 'id')->where('company_id', (int) session('company_id')),
            ],
        ]);
        $paymentMethod = isset($data['journal_id'])
            ? BankJournal::query()->with('paymentMethod.account')->findOrFail($data['journal_id'])->paymentMethod
            : $this->bankAccountOrFail((int) $data['payment_method_id']);
        abort_unless($paymentMethod, 422, 'El diario seleccionado no tiene una cuenta bancaria activa.');
        if (isset($data['journal_id'])) {
            $this->assertJournalBankAccount($paymentMethod);
        }

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
            'manual_reconcile_statement_id' => ['nullable', 'integer'],
            'reconciliation_mode' => ['nullable', 'in:invoice,account'],
            'third_party_id' => [
                'required_with:invoice',
                'required_if:reconciliation_mode,invoice',
                'nullable',
                'integer',
                Rule::exists('third_parties', 'id')->where('company_id', (int) session('company_id')),
            ],
            'invoice' => ['required_if:reconciliation_mode,invoice', 'prohibited_if:reconciliation_mode,account', 'nullable', 'string', 'max:40', 'regex:/^(sale|purchase|support_document):[1-9][0-9]*$/'],
            'amount' => ['required_if:matched_type,manual', 'numeric', 'gt:0'],
            'counter_account_id' => [
                Rule::requiredIf($request->input('matched_type') === 'manual' && ! $request->filled('invoice')),
                'nullable',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->where('company_id', (int) session('company_id')),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
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

        return DB::transaction(function () use ($data, $statement): RedirectResponse {
            $lockedStatement = BankStatement::query()->lockForUpdate()->findOrFail($statement->id);
            abort_if($lockedStatement->status !== 'pending', 409, 'El movimiento ya fue conciliado.');
            $bankAccount = $lockedStatement->paymentMethod;
            $bankPucAccount = $bankAccount?->account;
            if (! $bankPucAccount || ! $bankPucAccount->active || ! $bankPucAccount->allows_posting) {
                return $this->manualReconciliationError($lockedStatement->id, 'Asocia al movimiento una cuenta bancaria con cuenta PUC auxiliar activa.');
            }
            if (! str_starts_with($bankPucAccount->code, '11')) {
                return $this->manualReconciliationError($lockedStatement->id, 'La cuenta bancaria asociada debe pertenecer al grupo PUC 11.');
            }

            $amount = round((float) $data['amount'], 2);
            if ($amount !== round((float) $lockedStatement->amount, 2)) {
                return $this->manualReconciliationError($lockedStatement->id, 'El valor a conciliar debe ser exactamente $'.number_format((float) $lockedStatement->amount, 2, ',', '.').'.');
            }

            $thirdParty = isset($data['third_party_id'])
                ? ThirdParty::query()->findOrFail($data['third_party_id'])
                : null;
            $isCredit = $lockedStatement->transaction_type === 'credit';
            if ($thirdParty && ! ($isCredit ? $thirdParty->is_customer : $thirdParty->is_supplier)) {
                return $this->manualReconciliationError($lockedStatement->id, 'Selecciona un tercero '.($isCredit ? 'marcado como cliente' : 'marcado como proveedor').'.');
            }

            $invoice = null;
            if (filled($data['invoice'] ?? null)) {
                [$invoiceType, $invoiceId] = explode(':', $data['invoice'], 2);
                $invoice = match ($invoiceType) {
                    'sale' => Sale::query()->lockForUpdate()->findOrFail((int) $invoiceId),
                    'purchase' => Purchase::query()->lockForUpdate()->findOrFail((int) $invoiceId),
                    'support_document' => SupportDocument::query()->lockForUpdate()->findOrFail((int) $invoiceId),
                };
                if (($isCredit && ! ($invoice instanceof Sale)) || (! $isCredit && $invoice instanceof Sale)) {
                    return $this->manualReconciliationError($lockedStatement->id, 'La factura seleccionada no corresponde al tipo de movimiento bancario.');
                }
                if (! $thirdParty || ! $this->invoiceBelongsToThirdParty($invoice, $thirdParty)) {
                    return $this->manualReconciliationError($lockedStatement->id, 'La factura seleccionada no pertenece al tercero indicado.');
                }

                if ($invoice instanceof Purchase && $invoice->accountingVoucher()
                    ->whereHas('lines', fn ($query) => $query
                        ->where('credit', '>', 0)
                        ->whereHas('account', fn ($accountQuery) => $accountQuery->where('code', 'like', '11%')))
                    ->exists()) {
                    return $this->manualReconciliationError($lockedStatement->id, 'Esta compra ya tiene una salida de caja o banco contabilizada. Revisa el comprobante antes de registrar otro pago.');
                }

                $total = (float) ($invoice instanceof Purchase ? $invoice->total_pagar : $invoice->total);
                $balance = $invoice->balanceDue($total);
                if ($amount > round($balance, 2)) {
                    return $this->manualReconciliationError($lockedStatement->id, 'El abono supera el saldo pendiente de la factura ($'.number_format($balance, 2, ',', '.').').');
                }

                $counterAccount = isset($data['counter_account_id'])
                    ? ChartOfAccount::query()->whereKey($data['counter_account_id'])->where('active', true)->where('allows_posting', true)->first()
                    : $this->invoiceCounterAccount($invoice, $isCredit);
                if (! $counterAccount || $counterAccount->id === $bankPucAccount->id) {
                    return $this->manualReconciliationError($lockedStatement->id, 'No se pudo determinar una cuenta auxiliar de cartera o proveedores para esta factura. Revisa su contabilización y la configuración PUC.');
                }

                $this->periodService->assertWritable($isCredit ? 'income' : 'expenses', $lockedStatement->transaction_date->toDateString());
                $payment = $this->paymentPostingService->post(
                    $invoice,
                    $bankAccount,
                    $counterAccount,
                    $amount,
                    0,
                    [],
                    $lockedStatement->transaction_date->toDateString(),
                    $lockedStatement->reference,
                    $data['notes'] ?? null,
                );
                $voucher = $payment->accountingVoucher;
                $lockedStatement->update([
                    'status' => 'reconciled',
                    'matched_type' => 'payment',
                    'matched_id' => $payment->id,
                    'accounting_voucher_id' => $voucher->id,
                    'notes' => $data['notes'] ?? null,
                    'reconciled_at' => now(),
                    'reconciled_by' => auth()->id(),
                ]);

                return back()->with('success', 'Abono registrado y movimiento conciliado con el comprobante '.$voucher->consecutive.'.');
            }

            $counterAccount = ChartOfAccount::query()
                ->whereKey($data['counter_account_id'])
                ->where('active', true)
                ->where('allows_posting', true)
                ->first();
            if (! $counterAccount) {
                return $this->manualReconciliationError($lockedStatement->id, 'Selecciona una cuenta PUC auxiliar activa.');
            }
            if ((int) $counterAccount->id === (int) $bankPucAccount->id) {
                return $this->manualReconciliationError($lockedStatement->id, 'La cuenta de contrapartida debe ser distinta de la cuenta bancaria.');
            }

            $lines = collect([[
                'chart_of_account_id' => $bankPucAccount->id,
                'detail' => 'Movimiento bancario '.$lockedStatement->reference,
                'debit' => $isCredit ? $amount : 0,
                'credit' => $isCredit ? 0 : $amount,
            ], [
                'chart_of_account_id' => $counterAccount->id,
                'detail' => $lockedStatement->description ?: 'Contrapartida conciliación',
                'debit' => $isCredit ? 0 : $amount,
                'credit' => $isCredit ? $amount : 0,
            ]]);
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
    }

    private function invoiceCounterAccount(Sale|Purchase|SupportDocument $invoice, bool $isCredit): ?ChartOfAccount
    {
        $prefix = $isCredit ? '1305' : '2205';
        $invoice->loadMissing('accountingVoucher.lines.account');
        $accountsOnInvoice = $invoice->accountingVoucher?->lines
            ->filter(fn ($line): bool => ($isCredit ? $line->debit > 0 : $line->credit > 0)
                && $line->account?->active && $line->account?->allows_posting
                && str_starts_with($line->account->code, $prefix))
            ->pluck('account')
            ->unique('id');
        if ($accountsOnInvoice && $accountsOnInvoice->isNotEmpty()) {
            return $accountsOnInvoice->count() === 1 ? $accountsOnInvoice->first() : null;
        }

        $accounts = ChartOfAccount::query()
            ->where('code', 'like', $prefix.'%')
            ->where('active', true)
            ->where('allows_posting', true)
            ->limit(2)
            ->get();

        return $accounts->count() === 1 ? $accounts->first() : null;
    }

    public function transferCandidates(BankStatement $statement): JsonResponse
    {
        $candidates = BankStatement::query()
            ->with('paymentMethod.bankJournal')
            ->where('status', 'pending')
            ->where('transaction_type', $statement->transaction_type === 'credit' ? 'debit' : 'credit')
            ->where('amount', $statement->amount)
            ->whereNotNull('payment_method_id')
            ->where('payment_method_id', '<>', $statement->payment_method_id)
            ->orderByDesc('transaction_date')
            ->get()
            ->map(fn (BankStatement $candidate): array => [
                'id' => $candidate->id,
                'label' => $candidate->transaction_date->format('d/m/Y').' · '.($candidate->paymentMethod?->bankJournal?->name ?? $candidate->paymentMethod?->name).' · '.($candidate->reference ?: $candidate->description ?: 'Sin referencia'),
            ]);

        return response()->json(['movements' => $candidates]);
    }

    public function transfer(Request $request, BankStatement $statement): RedirectResponse
    {
        $data = $request->validate([
            'counter_statement_id' => ['required', 'integer', Rule::exists('bank_statements', 'id')->where('company_id', (int) session('company_id'))],
            'manual_reconcile_statement_id' => ['nullable', 'integer'],
            'reconciliation_mode' => ['nullable', 'in:transfer'],
        ]);

        return DB::transaction(function () use ($data, $statement): RedirectResponse {
            $ids = array_unique([$statement->id, (int) $data['counter_statement_id']]);
            sort($ids);
            $movements = BankStatement::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $origin = $movements->get($statement->id);
            $other = $movements->get((int) $data['counter_statement_id']);
            if (! $origin || ! $other || $origin->id === $other->id || $origin->status !== 'pending' || $other->status !== 'pending'
                || ! $origin->payment_method_id || ! $other->payment_method_id || $origin->payment_method_id === $other->payment_method_id
                || $origin->transaction_type === $other->transaction_type || $origin->amount !== $other->amount) {
                return $this->manualReconciliationError($statement->id, 'Selecciona dos movimientos pendientes de bancos distintos, con igual valor y tipos opuestos.');
            }

            foreach ([$origin, $other] as $movement) {
                $this->periodService->assertWritable('bank', $movement->transaction_date->toDateString());
            }
            $outgoing = $origin->transaction_type === 'debit' ? $origin : $other;
            $incoming = $origin->transaction_type === 'credit' ? $origin : $other;
            $sourceAccount = $outgoing->paymentMethod?->account;
            $destinationAccount = $incoming->paymentMethod?->account;
            if (! $sourceAccount || ! $destinationAccount || ! $sourceAccount->active || ! $destinationAccount->active
                || ! $sourceAccount->allows_posting || ! $destinationAccount->allows_posting
                || ! str_starts_with($sourceAccount->code, '11') || ! str_starts_with($destinationAccount->code, '11')
                || $sourceAccount->id === $destinationAccount->id) {
                return $this->manualReconciliationError($statement->id, 'Los dos diarios deben tener cuentas PUC bancarias auxiliares, activas y diferentes.');
            }
            $amount = (float) $origin->amount;
            $voucher = $this->accountingEntryService->post([
                'voucher_type' => 'egreso',
                'consecutive' => 'TRF-BNK-'.$outgoing->id.'-'.$incoming->id,
                'voucher_date' => $outgoing->transaction_date->toDateString(),
                'description' => 'Transferencia entre cuentas bancarias',
            ], collect([
                ['chart_of_account_id' => $sourceAccount->id, 'detail' => 'Salida por transferencia bancaria', 'debit' => 0, 'credit' => $amount],
                ['chart_of_account_id' => $destinationAccount->id, 'detail' => 'Ingreso por transferencia bancaria', 'debit' => $amount, 'credit' => 0],
            ]));

            foreach ([[$origin, $other], [$other, $origin]] as [$movement, $counterpart]) {
                $movement->update([
                    'status' => 'reconciled',
                    'matched_type' => 'transfer',
                    'matched_id' => $counterpart->id,
                    'accounting_voucher_id' => $voucher->id,
                    'reconciled_at' => now(),
                    'reconciled_by' => auth()->id(),
                ]);
            }

            return back()->with('success', 'Transferencia conciliada con el comprobante '.$voucher->consecutive.'.');
        });
    }

    private function invoiceBelongsToThirdParty(Sale|Purchase|SupportDocument $invoice, ThirdParty $thirdParty): bool
    {
        if ($invoice instanceof SupportDocument) {
            return (int) $invoice->third_party_id === (int) $thirdParty->id;
        }

        $invoiceDocument = $invoice instanceof Sale ? $invoice->customer_document : $invoice->provider_nit;
        $invoiceName = $invoice instanceof Sale ? $invoice->customer_name : $invoice->provider;

        if (filled($invoiceDocument) && filled($thirdParty->document)) {
            return $invoiceDocument === $thirdParty->document;
        }

        return $invoiceName === $thirdParty->name;
    }

    public function invoices(BankStatement $statement, ThirdParty $thirdParty): JsonResponse
    {
        $isCredit = $statement->transaction_type === 'credit';
        if (! $thirdParty->active || ! ($isCredit ? $thirdParty->is_customer : $thirdParty->is_supplier)) {
            return response()->json(['invoices' => []]);
        }

        if ($isCredit) {
            $invoices = Sale::query()
                ->where(function ($query) use ($thirdParty): void {
                    if (filled($thirdParty->document)) {
                        $query->where('customer_document', $thirdParty->document)
                            ->orWhere(function ($fallback) use ($thirdParty): void {
                                $fallback->where(function ($document) {
                                    $document->whereNull('customer_document')->orWhere('customer_document', '');
                                })->where('customer_name', $thirdParty->name);
                            });
                    } else {
                        $query->where('customer_name', $thirdParty->name);
                    }
                })
                ->withSum('payments as paid_amount', 'amount')
                ->withSum('creditNotes as credited_amount', 'total')
                ->whereRaw(
                    'sales.total > COALESCE((SELECT SUM(payments.amount) FROM payments WHERE payments.payable_type = ? AND payments.payable_id = sales.id), 0) + COALESCE((SELECT SUM(commercial_documents.total) FROM commercial_documents WHERE commercial_documents.reference_sale_id = sales.id AND commercial_documents.document_type = ? AND commercial_documents.company_id = ?), 0)',
                    [Sale::class, 'customer_credit_note', (int) session('company_id')],
                )
                ->latest('sale_date')
                ->get()
                ->map(function (Sale $sale): array {
                    $balance = round((float) $sale->total - (float) $sale->paid_amount - (float) $sale->credited_amount, 2);

                    return [
                        'value' => 'sale:'.$sale->id,
                        'date' => $sale->sale_date->toDateString(),
                        'label' => 'Factura '.$sale->invoice_number.' · '.$sale->sale_date->format('d/m/Y').' · Pendiente $'.number_format($balance, 2, ',', '.'),
                    ];
                })
                ->filter()
                ->values();

            return response()->json(['invoices' => $invoices]);
        }

        $purchases = Purchase::query()
            ->where(function ($query) use ($thirdParty): void {
                if (filled($thirdParty->document)) {
                    $query->where('provider_nit', $thirdParty->document)
                        ->orWhere(function ($fallback) use ($thirdParty): void {
                            $fallback->where(function ($document) {
                                $document->whereNull('provider_nit')->orWhere('provider_nit', '');
                            })->where('provider', $thirdParty->name);
                        });
                } else {
                    $query->where('provider', $thirdParty->name);
                }
            })
            ->withSum('payments as paid_amount', 'amount')
            ->whereRaw(
                'purchases.total_pagar > COALESCE((SELECT SUM(payments.amount) FROM payments WHERE payments.payable_type = ? AND payments.payable_id = purchases.id), 0)',
                [Purchase::class],
            )
            ->latest('purchase_date')
            ->get()
            ->map(function (Purchase $purchase): array {
                $balance = round((float) $purchase->total_pagar - (float) $purchase->paid_amount, 2);

                return [
                    'value' => 'purchase:'.$purchase->id,
                    'date' => $purchase->purchase_date->toDateString(),
                    'label' => 'Compra '.$purchase->invoice_number.' · '.$purchase->purchase_date->format('d/m/Y').' · Pendiente $'.number_format($balance, 2, ',', '.'),
                ];
            })
            ->filter()
            ->values();
        $supportDocuments = SupportDocument::query()
            ->where('third_party_id', $thirdParty->id)
            ->withSum('payments as paid_amount', 'amount')
            ->whereRaw(
                'support_documents.total > COALESCE((SELECT SUM(payments.amount) FROM payments WHERE payments.payable_type = ? AND payments.payable_id = support_documents.id), 0)',
                [SupportDocument::class],
            )
            ->latest('document_date')
            ->get()
            ->map(function (SupportDocument $document): array {
                $balance = round((float) $document->total - (float) $document->paid_amount, 2);

                return [
                    'value' => 'support_document:'.$document->id,
                    'date' => $document->document_date->toDateString(),
                    'label' => 'Documento soporte '.$document->consecutive.' · '.$document->document_date->format('d/m/Y').' · Pendiente $'.number_format($balance, 2, ',', '.'),
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'invoices' => $purchases->merge($supportDocuments)->sortByDesc('date')->values(),
        ]);
    }

    private function manualReconciliationError(int $statementId, string $message): RedirectResponse
    {
        return back()
            ->withErrors(['manual_reconcile' => $message])
            ->withInput()
            ->with('manual_reconcile_statement_id', $statementId);
    }

    public function unmatch(BankStatement $statement): RedirectResponse
    {
        $this->periodService->assertWritable('bank', $statement->transaction_date->toDateString());
        abort_if(in_array($statement->matched_type, ['manual', 'transfer'], true) && $statement->accounting_voucher_id, 409, 'El comprobante contable ya está publicado y debe conservarse. Registra un ajuste en lugar de desconciliarlo.');
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

    private function assertJournalBankAccount(?PaymentMethod $paymentMethod): void
    {
        $account = $paymentMethod?->account;
        abort_unless(
            $account && $account->active && $account->allows_posting && str_starts_with($account->code, '11'),
            422,
            'El diario debe estar asociado a una cuenta PUC auxiliar activa del grupo 11.',
        );
    }
}
