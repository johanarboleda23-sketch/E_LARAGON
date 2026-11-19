<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountingVoucher;
use App\Models\AccountingVoucherLine;
use App\Models\BankJournal;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\CommercialDocument;
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

    public function test_invoice_candidates_reflect_customer_debit_and_supplier_credit_notes(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $party = ThirdParty::create([
            'company_id' => $company->id, 'name' => 'Tercero con notas', 'document' => '123',
            'is_customer' => true, 'is_supplier' => true, 'active' => true,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id, 'invoice_number' => 'FV-NOTAS', 'customer_name' => $party->name,
            'customer_document' => $party->document, 'sale_date' => '2026-11-11', 'total' => 100,
        ]);
        $purchase = Purchase::create([
            'company_id' => $company->id, 'invoice_number' => 'FC-NOTAS', 'provider' => $party->name,
            'provider_nit' => $party->document, 'purchase_date' => '2026-11-11', 'total_pagar' => 100,
        ]);
        CommercialDocument::create([
            'company_id' => $company->id, 'document_type' => 'customer_debit_note', 'consecutive' => 'NDC-1',
            'third_party_name' => $party->name, 'document_date' => '2026-11-11', 'reference_sale_id' => $sale->id, 'total' => 20,
        ]);
        CommercialDocument::create([
            'company_id' => $company->id, 'document_type' => 'supplier_credit_note', 'consecutive' => 'NCP-1',
            'third_party_name' => $party->name, 'document_date' => '2026-11-11', 'reference_purchase_id' => $purchase->id, 'total' => 30,
        ]);
        $incoming = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit', 'amount' => 120, 'status' => 'pending',
        ]);
        $outgoing = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'debit', 'amount' => 70, 'status' => 'pending',
        ]);

        $this->get(route('bank-reconciliation.invoices', ['statement' => $incoming, 'thirdParty' => $party]))
            ->assertJsonPath('invoices.0.label', 'Factura FV-NOTAS · 11/11/2026 · Pendiente $120,00');
        $this->get(route('bank-reconciliation.invoices', ['statement' => $outgoing, 'thirdParty' => $party]))
            ->assertJsonPath('invoices.0.label', 'Compra FC-NOTAS · 11/11/2026 · Pendiente $70,00');
    }

    public function test_contact_can_be_assigned_to_a_manual_account_reconciliation_and_undone(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $party = ThirdParty::create([
            'company_id' => $company->id, 'name' => 'Contacto bancario', 'active' => true,
            'is_customer' => false, 'is_supplier' => false,
        ]);
        $account = ChartOfAccount::create([
            'company_id' => $company->id, 'code' => '519901', 'name' => 'Otros gastos',
            'class' => 5, 'nature' => 'debit', 'active' => true, 'allows_posting' => true,
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'debit', 'amount' => 100, 'status' => 'pending',
        ]);

        $this->get(route('bank-reconciliation.index'))->assertSee('Definir contacto')->assertSee('Contacto bancario');
        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual', 'reconciliation_mode' => 'contact', 'third_party_id' => $party->id,
            'amount' => 100, 'counter_account_id' => $account->id,
        ])->assertSessionHas('success');

        $voucher = $statement->fresh()->accountingVoucher;
        $this->assertSame('Contacto bancario', $voucher->third_party);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $voucher->id, 'chart_of_account_id' => $account->id, 'debit' => 100,
        ]);
        $this->get(route('bank-reconciliation.index'))->assertSee('Desconciliar y anular comprobante');
        $this->delete(route('bank-reconciliation.delete-voucher', $statement))->assertSessionHas('success');
        $this->assertSame('pending', $statement->fresh()->status);
        $this->assertSoftDeleted($voucher);
    }

    public function test_contact_reconciliation_rejects_missing_inactive_and_foreign_contacts(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $inactive = ThirdParty::create(['company_id' => $company->id, 'name' => 'Inactivo', 'active' => false]);
        $foreign = ThirdParty::create(['company_id' => $otherCompany->id, 'name' => 'Otro contacto', 'active' => true]);
        $account = ChartOfAccount::create([
            'company_id' => $company->id, 'code' => '519902', 'name' => 'Otros gastos',
            'class' => 5, 'nature' => 'debit', 'active' => true, 'allows_posting' => true,
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'debit', 'amount' => 100, 'status' => 'pending',
        ]);
        $payload = ['matched_type' => 'manual', 'reconciliation_mode' => 'contact', 'amount' => 100, 'counter_account_id' => $account->id];

        $this->post(route('bank-reconciliation.reconcile', $statement), $payload)->assertSessionHasErrors('third_party_id');
        $this->post(route('bank-reconciliation.reconcile', $statement), $payload + ['third_party_id' => $foreign->id])->assertSessionHasErrors('third_party_id');
        $this->post(route('bank-reconciliation.reconcile', $statement), $payload + ['third_party_id' => $inactive->id])->assertSessionHasErrors('manual_reconcile');
        $this->assertSame('pending', $statement->fresh()->status);
        $this->assertDatabaseCount('accounting_vouchers', 0);
    }

    public function test_invoice_reconciliation_applies_explicit_adjustment_without_changing_cash(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $receivable = ChartOfAccount::create([
            'company_id' => $company->id, 'code' => '130501', 'name' => 'Cartera',
            'class' => 1, 'nature' => 'debit', 'active' => true, 'allows_posting' => true,
        ]);
        $adjustment = ChartOfAccount::create([
            'company_id' => $company->id, 'code' => '539599', 'name' => 'Ajuste pesos',
            'class' => 5, 'nature' => 'debit', 'active' => true, 'allows_posting' => true,
        ]);
        $party = ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente ajuste', 'document' => '123',
            'is_customer' => true, 'active' => true]);
        $sale = Sale::create(['company_id' => $company->id, 'invoice_number' => 'FV-AJUSTE', 'customer_name' => $party->name,
            'customer_document' => $party->document, 'sale_date' => '2026-11-11', 'total' => 100]);
        $statement = BankStatement::create(['company_id' => $company->id, 'payment_method_id' => $bank->id,
            'transaction_date' => '2026-11-11', 'transaction_type' => 'credit', 'amount' => 99, 'status' => 'pending']);
        $payload = ['matched_type' => 'manual', 'reconciliation_mode' => 'invoice', 'third_party_id' => $party->id,
            'invoice' => 'sale:'.$sale->id, 'amount' => 99, 'counter_account_id' => $receivable->id,
            'adjustment_total' => 2, 'adjustment_account_id' => $adjustment->id];

        $this->post(route('bank-reconciliation.reconcile', $statement), $payload)->assertSessionHasErrors('manual_reconcile');
        $this->assertDatabaseCount('payments', 0);
        $payload['adjustment_total'] = 1;
        $this->post(route('bank-reconciliation.reconcile', $statement), $payload)->assertSessionHas('success');

        $payment = Payment::query()->firstOrFail();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'amount' => 99, 'adjustment_total' => 1]);
        $this->assertDatabaseHas('accounting_voucher_lines', ['accounting_voucher_id' => $payment->accounting_voucher_id,
            'chart_of_account_id' => $adjustment->id, 'debit' => 1, 'credit' => 0]);
        $this->assertDatabaseHas('accounting_voucher_lines', ['accounting_voucher_id' => $payment->accounting_voucher_id,
            'chart_of_account_id' => $receivable->id, 'credit' => 100]);
        $this->assertSame(0.0, $sale->fresh()->balanceDue(100));
        $this->get(route('bank-reconciliation.invoices', ['statement' => $statement, 'thirdParty' => $party]))
            ->assertJsonPath('invoices', []);
    }

    public function test_purchase_adjustment_keeps_the_unapplied_balance_open(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $payable = ChartOfAccount::create(['company_id' => $company->id, 'code' => '220501', 'name' => 'Proveedores',
            'class' => 2, 'nature' => 'credit', 'active' => true, 'allows_posting' => true]);
        $adjustment = ChartOfAccount::create(['company_id' => $company->id, 'code' => '429599', 'name' => 'Ajuste pesos',
            'class' => 4, 'nature' => 'credit', 'active' => true, 'allows_posting' => true]);
        $party = ThirdParty::create(['company_id' => $company->id, 'name' => 'Proveedor ajuste', 'document' => '321',
            'is_supplier' => true, 'active' => true]);
        $purchase = Purchase::create(['company_id' => $company->id, 'invoice_number' => 'COMP-AJUSTE', 'provider' => $party->name,
            'provider_nit' => $party->document, 'purchase_date' => '2026-11-11', 'total_pagar' => 200]);
        $statement = BankStatement::create(['company_id' => $company->id, 'payment_method_id' => $bank->id,
            'transaction_date' => '2026-11-11', 'transaction_type' => 'debit', 'amount' => 99, 'status' => 'pending']);

        $this->post(route('bank-reconciliation.reconcile', $statement), ['matched_type' => 'manual',
            'reconciliation_mode' => 'invoice', 'third_party_id' => $party->id, 'invoice' => 'purchase:'.$purchase->id,
            'amount' => 99, 'counter_account_id' => $payable->id, 'adjustment_total' => 1,
            'adjustment_account_id' => $adjustment->id])->assertSessionHas('success');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(100.0, $purchase->fresh()->balanceDue(200));
        $this->assertDatabaseHas('accounting_voucher_lines', ['accounting_voucher_id' => $payment->accounting_voucher_id,
            'chart_of_account_id' => $adjustment->id, 'credit' => 1, 'debit' => 0]);
        $this->get(route('bank-reconciliation.invoices', ['statement' => $statement, 'thirdParty' => $party]))
            ->assertJsonPath('invoices.0.label', 'Compra COMP-AJUSTE · 11/11/2026 · Pendiente $100,00');
    }

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

    public function test_excess_bank_amount_can_remain_pending_and_then_be_assigned_to_an_account(): void
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
        $advanceAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '280505',
            'name' => 'Anticipos recibidos',
            'class' => 2,
            'nature' => 'credit',
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

        $payload = [
            'matched_type' => 'manual',
            'manual_reconcile_statement_id' => $statement->id,
            'third_party_id' => $thirdParty->id,
            'invoice' => 'sale:'.$sale->id,
            'amount' => 301,
            'counter_account_id' => $counterAccount->id,
        ];
        $this->from(route('bank-reconciliation.index'))
            ->post(route('bank-reconciliation.reconcile', $statement), $payload)
            ->assertRedirect(route('bank-reconciliation.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('accounting_vouchers', 0);
        $this->assertSame('pending', $statement->fresh()->status);
        $this->assertSame('pending_sale', $statement->fresh()->matched_type);
        $this->assertSame(300.0, $sale->fresh()->balanceDue(300));
        $this->assertFalse(app(BankReconciliationService::class)->matchStatement($statement->fresh()));
        $this->get(route('bank-reconciliation.index'))->assertSeeText('Pendiente de distribución')
            ->assertSeeText('FV-SALDO-001')
            ->assertSee('data-old-invoice="sale:'.$sale->id.'"', false);

        $payload['excess_account_id'] = $bankAccount->chart_of_account_id;
        $this->post(route('bank-reconciliation.reconcile', $statement), $payload)
            ->assertSessionHasErrors('manual_reconcile');
        $this->assertDatabaseCount('payments', 0);
        $payload['excess_account_id'] = $advanceAccount->id;
        $this->post(route('bank-reconciliation.reconcile', $statement), $payload)
            ->assertSessionHas('success');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(300.0, (float) $payment->amount);
        $this->assertSame(0.0, $sale->fresh()->balanceDue(300));
        $this->assertSame('reconciled', $statement->fresh()->status);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $payment->accounting_voucher_id,
            'chart_of_account_id' => $advanceAccount->id,
            'credit' => 1,
        ]);
        $this->assertDatabaseHas('accounting_vouchers', [
            'id' => $payment->accounting_voucher_id,
            'total_debit' => 301,
            'total_credit' => 301,
        ]);

        $period = AccountingPeriod::create([
            'company_id' => $company->id, 'period_type' => 'month', 'period_key' => '2026-11',
            'period_start' => '2026-11-01', 'period_end' => '2026-11-30',
            'status' => 'closed', 'locked_modules' => ['bank'],
        ]);
        $this->delete(route('bank-reconciliation.delete-voucher', $statement))->assertConflict();
        $this->assertSame(0.0, $sale->fresh()->balanceDue(300));
        $this->assertSame('reconciled', $statement->fresh()->status);
        $period->update(['status' => 'open']);
        $this->delete(route('bank-reconciliation.delete-voucher', $statement))->assertSessionHas('success');

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        $this->assertSoftDeleted('accounting_vouchers', ['id' => $payment->accounting_voucher_id]);
        $this->assertSame(300.0, $sale->fresh()->balanceDue(300));
        $this->assertDatabaseHas('bank_statements', ['id' => $statement->id, 'status' => 'pending', 'matched_type' => null, 'accounting_voucher_id' => null]);
        $this->get(route('bank-reconciliation.invoices', ['statement' => $statement, 'thirdParty' => $thirdParty]))
            ->assertJsonPath('invoices.0.value', 'sale:'.$sale->id);
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

    public function test_supplier_overpayment_can_be_split_and_reversed_without_losing_the_invoice_balance(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);
        $payable = ChartOfAccount::create(['company_id' => $company->id, 'code' => '220501', 'name' => 'Proveedores',
            'class' => 2, 'nature' => 'credit', 'active' => true, 'allows_posting' => true]);
        $advance = ChartOfAccount::create(['company_id' => $company->id, 'code' => '133005', 'name' => 'Anticipos',
            'class' => 1, 'nature' => 'debit', 'active' => true, 'allows_posting' => true]);
        $party = ThirdParty::create(['company_id' => $company->id, 'name' => 'Proveedor exceso', 'is_supplier' => true, 'active' => true]);
        $purchase = Purchase::create(['company_id' => $company->id, 'invoice_number' => 'FC-EXCESO', 'provider' => $party->name,
            'purchase_date' => '2026-11-11', 'total_pagar' => 100]);
        $statement = BankStatement::create(['company_id' => $company->id, 'payment_method_id' => $bank->id,
            'transaction_date' => '2026-11-11', 'transaction_type' => 'debit', 'amount' => 150, 'status' => 'pending']);
        $payload = ['matched_type' => 'manual', 'reconciliation_mode' => 'invoice', 'third_party_id' => $party->id,
            'invoice' => 'purchase:'.$purchase->id, 'amount' => 150, 'applied_amount' => 50,
            'counter_account_id' => $payable->id, 'excess_account_id' => $advance->id];

        $this->post(route('bank-reconciliation.reconcile', $statement), $payload)->assertSessionHas('success');
        $payment = Payment::query()->firstOrFail();
        $this->assertSame(50.0, $purchase->fresh()->balanceDue(100));
        $this->assertDatabaseHas('accounting_voucher_lines', ['accounting_voucher_id' => $payment->accounting_voucher_id,
            'chart_of_account_id' => $advance->id, 'debit' => 100, 'credit' => 0]);
        $this->get(route('bank-reconciliation.index'))->assertSee('Eliminar comprobante y pago')->assertSee('Desconciliar');

        $this->delete(route('bank-reconciliation.delete-voucher', $statement))->assertSessionHas('success');
        $this->assertSame(100.0, $purchase->fresh()->balanceDue(100));
        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_another_company_cannot_delete_a_reconciliation_voucher(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $foreign = BankStatement::create(['company_id' => $otherCompany->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit', 'amount' => 10, 'status' => 'reconciled']);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->delete(route('bank-reconciliation.delete-voucher', $foreign))->assertNotFound();
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
            ->assertSee('Buscar tercero')
            ->assertSee('Factura pendiente')
            ->assertSee('Transferencia interna')
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

    public function test_invoice_modal_registers_payment_using_the_only_available_receivables_account(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $account = ChartOfAccount::create([
            'company_id' => $company->id, 'code' => '130501', 'name' => 'Clientes',
            'class' => 1, 'nature' => 'debit', 'allows_posting' => true, 'active' => true,
        ]);
        $party = ThirdParty::create([
            'company_id' => $company->id, 'name' => 'Cliente modal', 'document' => '901111111',
            'is_customer' => true, 'active' => true,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id, 'invoice_number' => 'FV-MODAL',
            'customer_name' => $party->name, 'customer_document' => $party->document,
            'sale_date' => '2026-11-11', 'total' => 500,
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit', 'amount' => 100, 'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual', 'reconciliation_mode' => 'invoice',
            'third_party_id' => $party->id, 'invoice' => 'sale:'.$sale->id, 'amount' => 100,
        ])->assertRedirect()->assertSessionHas('success');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame('reconciled', $statement->fresh()->status);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $payment->accounting_voucher_id,
            'chart_of_account_id' => $account->id, 'credit' => 100,
        ]);
        $this->assertSame(400.0, $sale->fresh()->balanceDue(500));
    }

    public function test_invoice_modal_does_not_post_without_an_unambiguous_receivables_account(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $party = ThirdParty::create([
            'company_id' => $company->id, 'name' => 'Cliente sin cuenta', 'document' => '902222222',
            'is_customer' => true, 'active' => true,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id, 'invoice_number' => 'FV-SIN-CUENTA',
            'customer_name' => $party->name, 'customer_document' => $party->document,
            'sale_date' => '2026-11-11', 'total' => 100,
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit', 'amount' => 100, 'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual', 'reconciliation_mode' => 'invoice',
            'third_party_id' => $party->id, 'invoice' => 'sale:'.$sale->id, 'amount' => 100,
        ])->assertRedirect()->assertSessionHasErrors('manual_reconcile');

        $this->assertSame('pending', $statement->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('accounting_vouchers', 0);
    }

    public function test_invoice_modal_requires_an_invoice_and_does_not_create_a_manual_voucher(): void
    {
        $company = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $party = ThirdParty::create([
            'company_id' => $company->id, 'name' => 'Cliente sin factura',
            'is_customer' => true, 'active' => true,
        ]);
        $statement = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit', 'amount' => 100, 'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('bank-reconciliation.invoices', ['statement' => $statement, 'thirdParty' => $party]))
            ->assertJsonCount(0, 'invoices');
        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual', 'reconciliation_mode' => 'invoice',
            'third_party_id' => $party->id, 'amount' => 100,
        ])->assertRedirect()->assertSessionHasErrors('invoice');

        $this->assertSame('pending', $statement->fresh()->status);
        $this->assertDatabaseCount('accounting_vouchers', 0);
    }

    public function test_internal_transfer_reconciles_both_bank_movements_with_one_voucher(): void
    {
        $company = Company::factory()->create();
        $source = $this->createBankAccount($company);
        $destinationAccount = ChartOfAccount::create([
            'company_id' => $company->id, 'code' => '111002', 'name' => 'Otro banco',
            'class' => 1, 'nature' => 'debit', 'allows_posting' => true, 'active' => true,
        ]);
        $destination = PaymentMethod::create([
            'company_id' => $company->id, 'name' => 'Banco destino', 'chart_of_account_id' => $destinationAccount->id,
            'is_cash' => false, 'is_editable' => true,
        ]);
        $outgoing = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $source->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'debit', 'amount' => 5000, 'status' => 'pending',
        ]);
        $incoming = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $destination->id, 'transaction_date' => '2026-11-12',
            'transaction_type' => 'credit', 'amount' => 5000, 'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('bank-reconciliation.transfer-candidates', $outgoing))
            ->assertJsonPath('movements.0.id', $incoming->id);
        $this->post(route('bank-reconciliation.transfer', $outgoing), [
            'counter_statement_id' => $incoming->id, 'reconciliation_mode' => 'transfer',
        ])->assertRedirect()->assertSessionHas('success');

        $voucherId = $outgoing->fresh()->accounting_voucher_id;
        $this->assertDatabaseHas('bank_statements', ['id' => $outgoing->id, 'status' => 'reconciled', 'matched_type' => 'transfer', 'matched_id' => $incoming->id, 'accounting_voucher_id' => $voucherId]);
        $this->assertDatabaseHas('bank_statements', ['id' => $incoming->id, 'status' => 'reconciled', 'matched_type' => 'transfer', 'matched_id' => $outgoing->id, 'accounting_voucher_id' => $voucherId]);
        $this->assertDatabaseHas('accounting_voucher_lines', ['accounting_voucher_id' => $voucherId, 'chart_of_account_id' => $source->chart_of_account_id, 'credit' => 5000]);
        $this->assertDatabaseHas('accounting_voucher_lines', ['accounting_voucher_id' => $voucherId, 'chart_of_account_id' => $destinationAccount->id, 'debit' => 5000]);
        $this->assertDatabaseCount('accounting_vouchers', 1);
        $this->post(route('bank-reconciliation.unmatch', $incoming))->assertConflict();
        $this->delete(route('bank-reconciliation.delete-voucher', $incoming))->assertSessionHas('success');
        $this->assertSoftDeleted('accounting_vouchers', ['id' => $voucherId]);
        $this->assertDatabaseHas('bank_statements', ['id' => $outgoing->id, 'status' => 'pending', 'accounting_voucher_id' => null]);
        $this->assertDatabaseHas('bank_statements', ['id' => $incoming->id, 'status' => 'pending', 'accounting_voucher_id' => null]);
        $this->post(route('bank-reconciliation.transfer', $outgoing), [
            'counter_statement_id' => $incoming->id, 'reconciliation_mode' => 'transfer',
        ])->assertSessionHas('success');
    }

    public function test_internal_transfer_rejects_mismatched_amounts_without_posting(): void
    {
        $company = Company::factory()->create();
        $source = $this->createBankAccount($company);
        $other = PaymentMethod::create(['company_id' => $company->id, 'name' => 'Otro banco', 'is_cash' => false, 'is_editable' => true]);
        $outgoing = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $source->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'debit', 'amount' => 100, 'status' => 'pending',
        ]);
        $incoming = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $other->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit', 'amount' => 101, 'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('bank-reconciliation.transfer-candidates', $outgoing))->assertJsonCount(0, 'movements');
        $this->post(route('bank-reconciliation.transfer', $outgoing), [
            'counter_statement_id' => $incoming->id,
        ])->assertRedirect()->assertSessionHasErrors('manual_reconcile');

        $this->assertSame('pending', $outgoing->fresh()->status);
        $this->assertSame('pending', $incoming->fresh()->status);
        $this->assertDatabaseCount('accounting_vouchers', 0);
    }

    public function test_internal_transfer_rejects_a_movement_from_another_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $bank = $this->createBankAccount($company);
        $statement = BankStatement::create([
            'company_id' => $company->id, 'payment_method_id' => $bank->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'debit', 'amount' => 100, 'status' => 'pending',
        ]);
        $foreign = BankStatement::create([
            'company_id' => $otherCompany->id, 'transaction_date' => '2026-11-11',
            'transaction_type' => 'credit', 'amount' => 100, 'status' => 'pending',
        ]);
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->post(route('bank-reconciliation.transfer', $statement), [
            'counter_statement_id' => $foreign->id,
        ])->assertRedirect()->assertSessionHasErrors('counter_statement_id');

        $this->assertSame('pending', $statement->fresh()->status);
        $this->assertDatabaseCount('accounting_vouchers', 0);
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
