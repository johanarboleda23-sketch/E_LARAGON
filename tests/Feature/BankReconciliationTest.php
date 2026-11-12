<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountingVoucher;
use App\Models\AccountingVoucherLine;
use App\Models\BankJournal;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use App\Models\User;
use App\Services\BankReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_and_reconciles_a_bank_movement(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $csv = "fecha;referencia;descripcion;tipo;importe\n2026-10-01;TRX-001;Pago proveedor;debit;250000\n";
        $this->post(route('bank-reconciliation.import'), [
            'file' => UploadedFile::fake()->createWithContent('extracto.csv', $csv),
            'payment_method_id' => $bankAccount->id,
        ])->assertRedirect();

        $statement = BankStatement::query()->firstOrFail();
        $this->assertSame('debit', $statement->transaction_type);
        $this->assertSame('pending', $statement->status);
        $this->assertSame($bankAccount->id, $statement->payment_method_id);
        $expenseAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => 'EXP-MANUAL',
            'name' => 'Gasto manual',
            'class' => 5,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);

        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual',
            'notes' => 'Revisado contra extracto bancario.',
            'amount' => 250000,
            'counter_account_id' => $expenseAccount->id,
        ])->assertRedirect();

        $this->assertSame('reconciled', $statement->fresh()->status);
        $this->assertNotNull($statement->fresh()->reconciled_at);
        $this->assertNotNull($statement->fresh()->accounting_voucher_id);
        $this->assertDatabaseHas('accounting_vouchers', [
            'id' => $statement->fresh()->accounting_voucher_id,
            'voucher_type' => 'egreso',
            'total_debit' => 250000,
            'total_credit' => 250000,
        ]);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $statement->fresh()->accounting_voucher_id,
            'chart_of_account_id' => $expenseAccount->id,
            'debit' => 250000,
            'credit' => 0,
        ]);
    }

    public function test_it_creates_a_unique_diary_for_a_bank_account_and_imports_to_it(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('bank-reconciliation.journals'))
            ->assertOk()
            ->assertSee('Crear diario bancario')
            ->assertSee('Diarios configurados');
        $this->from(route('bank-reconciliation.journals'))
            ->post(route('bank-reconciliation.journals.store'), [
                'name' => 'Bancolombia principal',
                'payment_method_id' => $bankAccount->id,
            ])
            ->assertRedirect(route('bank-reconciliation.journals'));
        $journal = BankJournal::query()->firstOrFail();
        $this->assertSame($bankAccount->id, $journal->payment_method_id);
        $this->get(route('bank-reconciliation.index'))->assertSee('Bancolombia principal');

        $this->from(route('bank-reconciliation.journals'))
            ->post(route('bank-reconciliation.journals.store'), [
                'name' => 'Bancolombia otra vez',
                'payment_method_id' => $bankAccount->id,
            ])
            ->assertRedirect(route('bank-reconciliation.journals'))
            ->assertSessionHasErrors('payment_method_id');
        $this->assertDatabaseCount('bank_journals', 1);

        $csv = "Fecha;Descripción;Valor\n2026-11-11;Consignación;25000\n";
        $this->post(route('bank-reconciliation.import'), [
            'file' => UploadedFile::fake()->createWithContent('extracto.csv', $csv),
            'journal_id' => $journal->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_statements', [
            'payment_method_id' => $bankAccount->id,
            'description' => 'Consignación',
            'amount' => 25000,
        ]);
    }

    public function test_it_registers_a_partial_invoice_payment_and_reconciles_the_bank_movement(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $customerAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '130501',
            'name' => 'Clientes nacionales',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'name' => 'Cliente de prueba',
            'document' => '900123456',
            'is_customer' => true,
            'active' => true,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-0001',
            'customer_name' => $thirdParty->name,
            'customer_document' => $thirdParty->document,
            'sale_date' => '2026-11-11',
            'total' => 300,
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-11-11',
            'reference' => 'TRX-CLIENTE-001',
            'description' => 'Abono de cliente',
            'transaction_type' => 'credit',
            'amount' => 100,
            'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('bank-reconciliation.invoices', [
            'statement' => $statement,
            'thirdParty' => $thirdParty,
        ]))
            ->assertOk()
            ->assertJsonPath('invoices.0.value', 'sale:'.$sale->id)
            ->assertJsonPath('invoices.0.label', 'Factura FV-0001 · 11/11/2026 · Pendiente $300,00');

        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual',
            'manual_reconcile_statement_id' => $statement->id,
            'third_party_id' => $thirdParty->id,
            'invoice' => 'sale:'.$sale->id,
            'amount' => 100,
            'counter_account_id' => $customerAccount->id,
            'notes' => 'Abono parcial',
        ])->assertRedirect()
            ->assertSessionHas('success');

        $payment = Payment::query()->firstOrFail();
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'amount' => 100,
            'payment_method_id' => $bankAccount->id,
            'reference' => 'TRX-CLIENTE-001',
        ]);
        $this->assertDatabaseHas('bank_statements', [
            'id' => $statement->id,
            'status' => 'reconciled',
            'matched_type' => 'payment',
            'matched_id' => $payment->id,
            'accounting_voucher_id' => $payment->accounting_voucher_id,
        ]);
        $this->assertDatabaseHas('accounting_vouchers', [
            'id' => $payment->accounting_voucher_id,
            'voucher_type' => 'recibo_caja',
            'total_debit' => 100,
            'total_credit' => 100,
        ]);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $payment->accounting_voucher_id,
            'chart_of_account_id' => $customerAccount->id,
            'credit' => 100,
        ]);
        $this->get(route('bank-reconciliation.invoices', [
            'statement' => $statement,
            'thirdParty' => $thirdParty,
        ]))
            ->assertJsonPath('invoices.0.label', 'Factura FV-0001 · 11/11/2026 · Pendiente $200,00');
    }

    public function test_invoice_reconciliation_rejects_a_bank_amount_above_the_open_invoice_balance(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $counterAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '130502',
            'name' => 'Clientes de prueba',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'name' => 'Cliente con saldo menor',
            'document' => '900654321',
            'is_customer' => true,
            'active' => true,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-SALDO-001',
            'customer_name' => $thirdParty->name,
            'customer_document' => $thirdParty->document,
            'sale_date' => '2026-11-11',
            'total' => 300,
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit',
            'amount' => 301,
            'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->from(route('bank-reconciliation.index'))
            ->post(route('bank-reconciliation.reconcile', $statement), [
                'matched_type' => 'manual',
                'manual_reconcile_statement_id' => $statement->id,
                'third_party_id' => $thirdParty->id,
                'invoice' => 'sale:'.$sale->id,
                'amount' => 301,
                'counter_account_id' => $counterAccount->id,
            ])
            ->assertRedirect(route('bank-reconciliation.index'))
            ->assertSessionHasErrors([
                'manual_reconcile' => 'El abono supera el saldo pendiente de la factura ($300,00).',
            ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('accounting_vouchers', 0);
        $this->assertSame('pending', $statement->fresh()->status);
    }

    public function test_it_registers_payments_for_purchase_and_support_document_invoices(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $payablesAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '220501',
            'name' => 'Proveedores nacionales',
            'class' => 2,
            'nature' => 'credit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'name' => 'Proveedor de prueba',
            'document' => '800123456',
            'is_supplier' => true,
            'active' => true,
        ]);
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-0001',
            'provider' => $thirdParty->name,
            'provider_nit' => $thirdParty->document,
            'purchase_date' => '2026-11-11',
            'total_pagar' => 100,
        ]);
        $supportDocument = SupportDocument::create([
            'company_id' => $company->id,
            'third_party_id' => $thirdParty->id,
            'consecutive' => 'DS-0001',
            'document_date' => '2026-11-11',
            'concept' => 'Servicios de prueba',
            'subtotal' => 75,
            'total' => 75,
        ]);
        $purchaseStatement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-11-11',
            'reference' => 'TRX-PROV-001',
            'transaction_type' => 'debit',
            'amount' => 40,
            'status' => 'pending',
        ]);
        $supportStatement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-11-11',
            'reference' => 'TRX-PROV-002',
            'transaction_type' => 'debit',
            'amount' => 25,
            'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('bank-reconciliation.invoices', [
            'statement' => $purchaseStatement,
            'thirdParty' => $thirdParty,
        ]))
            ->assertOk()
            ->assertJsonFragment(['value' => 'purchase:'.$purchase->id])
            ->assertJsonFragment(['value' => 'support_document:'.$supportDocument->id]);

        foreach ([
            [$purchaseStatement, 'purchase:'.$purchase->id],
            [$supportStatement, 'support_document:'.$supportDocument->id],
        ] as [$statement, $invoice]) {
            $this->post(route('bank-reconciliation.reconcile', $statement), [
                'matched_type' => 'manual',
                'third_party_id' => $thirdParty->id,
                'invoice' => $invoice,
                'amount' => $statement->amount,
                'counter_account_id' => $payablesAccount->id,
            ])->assertRedirect()
                ->assertSessionHas('success');
        }

        $this->assertDatabaseHas('payments', [
            'payable_type' => Purchase::class,
            'payable_id' => $purchase->id,
            'amount' => 40,
            'reference' => 'TRX-PROV-001',
        ]);
        $this->assertDatabaseHas('payments', [
            'payable_type' => SupportDocument::class,
            'payable_id' => $supportDocument->id,
            'amount' => 25,
            'reference' => 'TRX-PROV-002',
        ]);
        $this->assertDatabaseCount('accounting_vouchers', 2);
        $this->assertDatabaseHas('accounting_vouchers', [
            'voucher_type' => 'egreso',
            'total_debit' => 40,
            'total_credit' => 40,
        ]);
        $this->assertDatabaseHas('accounting_vouchers', [
            'voucher_type' => 'egreso',
            'total_debit' => 25,
            'total_credit' => 25,
        ]);
        $this->assertSame('reconciled', $purchaseStatement->fresh()->status);
        $this->assertSame('reconciled', $supportStatement->fresh()->status);
    }

    public function test_it_imports_the_three_column_excel_export_shape_as_credit_or_debit(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $csv = "Fecha;Descripción;Valor\n46292.208333333328;TRANSFERENCIA CTA SUC VIRTUAL;-50000\n46291.208333333328;CONSIGNACION CORRESPONSAL CB;425000\n";
        $this->post(route('bank-reconciliation.import'), [
            'file' => UploadedFile::fake()->createWithContent('joh.csv', $csv),
            'payment_method_id' => $bankAccount->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_statements', [
            'description' => 'TRANSFERENCIA CTA SUC VIRTUAL',
            'transaction_type' => 'debit',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('bank_statements', [
            'description' => 'CONSIGNACION CORRESPONSAL CB',
            'transaction_type' => 'credit',
            'amount' => 425000,
        ]);
    }

    public function test_it_imports_bancolombia_four_column_excel_files(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $filePath = tempnam(sys_get_temp_dir(), 'bank-statement-test-');
        $writer = new Writer(new Options);
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues(['Fecha', 'Descripción', 'Referencia', 'Valor']));
        $writer->addRow(Row::fromValues(['2026-11-11', 'Débito de prueba', 'REF-001', -15000]));
        $writer->addRow(Row::fromValues(['2026-11-11', 'Crédito de prueba', 'REF-002', 23000]));
        $writer->close();
        $file = UploadedFile::fake()->createWithContent('movimientos-bancolombia.xlsx', file_get_contents($filePath));
        unlink($filePath);

        $this->post(route('bank-reconciliation.import'), [
            'file' => $file,
            'payment_method_id' => $bankAccount->id,
        ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Se importaron 2 movimientos bancarios.');

        $this->assertDatabaseHas('bank_statements', [
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-11-11 00:00:00',
            'reference' => 'REF-001',
            'description' => 'Débito de prueba',
            'transaction_type' => 'debit',
            'amount' => 15000,
        ]);
        $this->assertDatabaseHas('bank_statements', [
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-11-11 00:00:00',
            'reference' => 'REF-002',
            'description' => 'Crédito de prueba',
            'transaction_type' => 'credit',
            'amount' => 23000,
        ]);
    }

    public function test_manual_reconciliation_rejects_an_amount_that_differs_from_the_statement(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $expenseAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => 'EXP-MANUAL',
            'name' => 'Gasto manual',
            'class' => 5,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $statement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-11-11',
            'reference' => 'MANUAL-001',
            'description' => 'Pago de prueba',
            'transaction_type' => 'debit',
            'amount' => 100,
            'status' => 'pending',
        ]);

        $this->get(route('bank-reconciliation.index'))
            ->assertSee('Tercero')
            ->assertSee('Factura por pagar (opcional)')
            ->assertSee('Valor a conciliar')
            ->assertSee('Cuenta PUC de contrapartida');

        $this->from(route('bank-reconciliation.index'))
            ->post(route('bank-reconciliation.reconcile', $statement), [
                'matched_type' => 'manual',
                'manual_reconcile_statement_id' => $statement->id,
                'amount' => 90,
                'counter_account_id' => $expenseAccount->id,
            ])
            ->assertRedirect(route('bank-reconciliation.index'))
            ->assertSessionHasErrors([
                'manual_reconcile' => 'El valor a conciliar debe ser exactamente $100,00.',
            ])
            ->assertSessionHasInput('manual_reconcile_statement_id', $statement->id)
            ->assertSessionHasInput('amount', 90);

        $this->assertSame('pending', $statement->fresh()->status);
        $this->assertDatabaseCount('accounting_vouchers', 0);
    }

    public function test_it_calculates_bank_and_ledger_closing_balances_from_the_opening_balance(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->post(route('bank-reconciliation.opening-balances.store'), [
            'payment_method_id' => $bankAccount->id,
            'effective_date' => '2026-10-01',
            'opening_balance' => 1000,
        ])->assertRedirect();

        BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-10-01',
            'transaction_type' => 'credit',
            'amount' => 200,
            'status' => 'reconciled',
        ]);
        BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => '2026-10-02',
            'transaction_type' => 'debit',
            'amount' => 50,
            'status' => 'pending',
        ]);
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'voucher_type' => 'ajuste_contable',
            'consecutive' => 'AJ-BANK-1',
            'voucher_date' => '2026-10-01',
            'total_debit' => 200,
            'total_credit' => 200,
        ]);
        AccountingVoucherLine::create([
            'company_id' => $company->id,
            'accounting_voucher_id' => $voucher->id,
            'chart_of_account_id' => $bankAccount->chart_of_account_id,
            'debit' => 200,
            'credit' => 0,
        ]);
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'voucher_type' => 'egreso',
            'consecutive' => 'EG-BANK-1',
            'voucher_date' => '2026-10-02',
            'total_debit' => 50,
            'total_credit' => 50,
        ]);
        AccountingVoucherLine::create([
            'company_id' => $company->id,
            'accounting_voucher_id' => $voucher->id,
            'chart_of_account_id' => $bankAccount->chart_of_account_id,
            'debit' => 0,
            'credit' => 50,
        ]);

        $this->assertDatabaseHas('bank_statements', [
            'payment_method_id' => $bankAccount->id,
            'transaction_type' => 'debit',
            'amount' => 50,
        ]);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $voucher->id,
            'chart_of_account_id' => $bankAccount->chart_of_account_id,
            'credit' => 50,
        ]);

        $this->get(route('bank-reconciliation.balances', [
            'payment_method_id' => $bankAccount->id,
            'date' => '2026-10-02',
        ]))
            ->assertOk()
            ->assertSee('$1,150.00')
            ->assertSee('$0.00');
    }

    public function test_daily_balances_only_list_payment_methods_configured_as_bank_accounts(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        PaymentMethod::create([
            'name' => 'Método sin cuenta bancaria',
            'bank_name' => '',
            'account_number' => '',
            'is_cash' => false,
            'is_editable' => true,
        ]);

        $this->get(route('bank-reconciliation.balances'))
            ->assertSee('Cuenta corriente prueba')
            ->assertDontSee('Método sin cuenta bancaria');
    }

    public function test_automatic_match_leaves_statements_pending_when_the_accounting_period_is_closed(): void
    {
        $company = Company::factory()->create();
        $bankAccount = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $date = now()->toDateString();
        AccountingPeriod::create([
            'company_id' => $company->id,
            'period_type' => 'month',
            'period_key' => now()->format('Y-m'),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'closed',
            'locked_modules' => ['bank'],
        ]);
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-CLOSED-PERIOD',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => $date,
            'total' => 250,
        ]);
        Payment::create([
            'company_id' => $company->id,
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'amount' => 250,
            'payment_date' => $date,
            'method' => 'Transferencia',
            'payment_method_id' => $bankAccount->id,
            'reference' => 'CLOSED-001',
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $bankAccount->id,
            'transaction_date' => $date,
            'reference' => 'CLOSED-001',
            'transaction_type' => 'credit',
            'amount' => 250,
            'status' => 'pending',
        ]);

        $matched = app(BankReconciliationService::class)->matchStatement($statement);

        $this->assertFalse($matched);
        $this->assertSame('pending', $statement->fresh()->status);
    }

    private function createBankAccount(Company $company): PaymentMethod
    {
        session(['company_id' => $company->id]);

        $account = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '111001',
            'name' => 'Banco de prueba',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);

        return PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Cuenta corriente prueba',
            'bank_name' => 'Banco de prueba',
            'account_number' => '123456789',
            'chart_of_account_id' => $account->id,
            'is_cash' => false,
            'is_editable' => true,
        ]);
    }
}
