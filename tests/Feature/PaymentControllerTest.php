<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountingVoucher;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\CommercialDocument;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_partial_payment_reduces_the_sale_balance_without_marking_it_fully_paid(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-PAY-1',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 1000,
        ]);
        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee(route('sales.statement', $sale).'#payment-'.$sale->id, false)
            ->assertSee('Registrar pago · Recibo de caja');
        $paymentDetails = $this->paymentDetails($company, 'PAY1305');

        $response = $this->post(route('sales.payments.store', $sale), [
            'amount' => 400,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
        ]);
        $payment = $sale->payments()->firstOrFail();

        $response->assertRedirect(route('accounting.vouchers.accounting', $payment->accountingVoucher));

        $this->assertSame(400.0, $sale->paidAmount());
        $this->assertSame(600.0, $sale->balanceDue((float) $sale->total));

        $this->get(route('accounting.vouchers.accounting', $payment->accountingVoucher))
            ->assertOk()
            ->assertSee('Pago registrado con comprobante '.$payment->accountingVoucher->consecutive);

        $this->get(route('sales.statement', $sale))
            ->assertOk()
            ->assertSee('Abono parcial')
            ->assertSee('Caja de prueba')
            ->assertSee($payment->accountingVoucher->consecutive);
    }

    public function test_payment_entry_screen_preloads_sale_purchase_and_support_documents(): void
    {
        $company = $this->authenticateWithCompany();
        $this->paymentDetails($company, 'PAY1305');
        ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '220501',
            'name' => 'Proveedores nacionales',
            'class' => 2,
            'nature' => 'credit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $supplier = ThirdParty::create([
            'company_id' => $company->id,
            'name' => 'Proveedor de prueba',
            'document' => '800123456',
            'is_supplier' => true,
            'active' => true,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-PAY-ENTRY',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'total' => 125,
        ]);
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-PAY-ENTRY',
            'provider' => $supplier->name,
            'purchase_date' => now()->toDateString(),
            'total_pagar' => 75,
        ]);
        $supportDocument = SupportDocument::create([
            'company_id' => $company->id,
            'third_party_id' => $supplier->id,
            'consecutive' => 'DS-PAY-ENTRY',
            'document_date' => now()->toDateString(),
            'concept' => 'Servicios de prueba',
            'subtotal' => 60,
            'total' => 60,
        ]);

        $this->get(route('sales.payments.create', $sale))
            ->assertOk()
            ->assertSee('FV-PAY-ENTRY')
            ->assertSee('Cliente de prueba')
            ->assertSee('125.00');
        $this->get(route('purchases.payments.create', $purchase))
            ->assertOk()
            ->assertSee('FC-PAY-ENTRY')
            ->assertSee('Proveedor de prueba')
            ->assertSee('75.00');
        $this->get(route('support-documents.payments.create', $supportDocument))
            ->assertOk()
            ->assertSee('DS-PAY-ENTRY')
            ->assertSee('Proveedor de prueba')
            ->assertSee('60.00');
    }

    public function test_additional_withholding_reduces_the_balance_and_reconciles_only_the_cash_amount(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-WITHHOLDING',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'total' => 400,
        ]);
        $details = $this->paymentDetails($company, 'PAY1305', false, 'TRX-WITHHOLDING');
        $withholdingAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '1355100',
            'name' => 'Retenciones practicadas por clientes',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $bankStatement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $details['payment_method_id'],
            'transaction_date' => now()->toDateString(),
            'reference' => 'TRX-WITHHOLDING',
            'transaction_type' => 'credit',
            'amount' => 300,
            'status' => 'pending',
        ]);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 300,
            'payment_date' => now()->toDateString(),
            ...$details,
            'withholdings' => [[
                'chart_of_account_id' => $withholdingAccount->id,
                'amount' => 100,
            ]],
        ])->assertRedirect()->assertSessionHas('success', fn (string $message) => str_contains($message, 'conciliado automáticamente'));

        $payment = $sale->payments()->firstOrFail();
        $voucher = $payment->accountingVoucher;
        $this->assertSame(300.0, (float) $payment->amount);
        $this->assertSame(100.0, (float) $payment->withholding_total);
        $this->assertSame(400.0, $sale->fresh()->paidAmount());
        $this->assertSame(0.0, $sale->fresh()->balanceDue(400));
        $this->assertDatabaseHas('accounting_vouchers', [
            'id' => $voucher->id,
            'total_debit' => 400,
            'total_credit' => 400,
        ]);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $voucher->id,
            'chart_of_account_id' => $withholdingAccount->id,
            'debit' => 100,
            'credit' => 0,
        ]);
        $this->assertDatabaseHas('bank_statements', [
            'id' => $bankStatement->id,
            'status' => 'reconciled',
            'matched_type' => 'payment',
            'matched_id' => $payment->id,
        ]);
    }

    public function test_additional_withholding_cannot_make_the_applied_amount_exceed_the_balance(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-WITHHOLDING-LIMIT',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'total' => 400,
        ]);
        $details = $this->paymentDetails($company, 'PAY1305');
        $withholdingAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '1355100',
            'name' => 'Retenciones practicadas por clientes',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 300,
            'payment_date' => now()->toDateString(),
            ...$details,
            'withholdings' => [[
                'chart_of_account_id' => $withholdingAccount->id,
                'amount' => 100.01,
            ]],
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(400.0, $sale->fresh()->balanceDue(400));
    }

    public function test_malformed_additional_withholding_rows_are_rejected(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-WITHHOLDING-INVALID',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'total' => 400,
        ]);
        $paymentDetails = $this->paymentDetails($company, 'PAY1305');

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 300,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
            'withholdings' => ['fila inválida'],
        ])->assertSessionHasErrors('withholdings.0');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_support_document_payment_records_manual_withholding_in_an_expense_voucher(): void
    {
        $company = $this->authenticateWithCompany();
        $supplier = ThirdParty::create([
            'company_id' => $company->id,
            'name' => 'Proveedor de prueba',
            'document' => '800123456',
            'is_supplier' => true,
            'active' => true,
        ]);
        $supportDocument = SupportDocument::create([
            'company_id' => $company->id,
            'third_party_id' => $supplier->id,
            'consecutive' => 'DS-PAYMENT',
            'document_date' => now()->toDateString(),
            'concept' => 'Servicios de prueba',
            'subtotal' => 100,
            'total' => 100,
        ]);
        $withholdingAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '2365100',
            'name' => 'Retención en la fuente practicada',
            'class' => 2,
            'nature' => 'credit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $paymentDetails = $this->paymentDetails($company, 'PAY2205');

        $this->post(route('support-documents.payments.store', $supportDocument), [
            'amount' => 80,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
            'withholdings' => [[
                'chart_of_account_id' => $withholdingAccount->id,
                'amount' => 20,
            ]],
        ])->assertRedirect();

        $payment = $supportDocument->payments()->firstOrFail();
        $voucher = $payment->accountingVoucher;
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'payable_type' => SupportDocument::class,
            'payable_id' => $supportDocument->id,
            'amount' => 80,
            'withholding_total' => 20,
        ]);
        $this->assertDatabaseHas('accounting_vouchers', [
            'id' => $voucher->id,
            'voucher_type' => 'egreso',
            'total_debit' => 100,
            'total_credit' => 100,
        ]);
        $this->assertDatabaseHas('accounting_voucher_lines', [
            'accounting_voucher_id' => $voucher->id,
            'chart_of_account_id' => $withholdingAccount->id,
            'debit' => 0,
            'credit' => 20,
        ]);
    }

    public function test_a_payment_cannot_exceed_the_pending_balance(): void
    {
        $company = $this->authenticateWithCompany();
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-PAY-1',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 500,
            'iva_total' => 0,
            'retefuente' => 0,
            'total_pagar' => 500,
        ]);
        $this->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee(route('purchases.statement', $purchase).'#payment-'.$purchase->id, false)
            ->assertSee('Registrar pago · Egreso');
        $paymentDetails = $this->paymentDetails($company, 'PAY2205');

        $this->post(route('purchases.payments.store', $purchase), [
            'amount' => 500.01,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0.0, $purchase->paidAmount());
    }

    public function test_purchase_with_a_recorded_bank_outflow_requires_review_before_another_payment(): void
    {
        $company = $this->authenticateWithCompany();
        $bank = ChartOfAccount::create([
            'code' => '111001',
            'name' => 'Banco de prueba',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $voucher = AccountingVoucher::create([
            'voucher_type' => 'compra',
            'consecutive' => 'COMP-REVISION',
            'voucher_date' => now()->toDateString(),
            'total_debit' => 500,
            'total_credit' => 500,
        ]);
        $voucher->lines()->create([
            'chart_of_account_id' => $bank->id,
            'debit' => 0,
            'credit' => 500,
        ]);
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-REVISION',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => now()->toDateString(),
            'total_pagar' => 500,
            'accounting_voucher_id' => $voucher->id,
        ]);
        $paymentDetails = $this->paymentDetails($company, 'PAY2205');

        $this->post(route('purchases.payments.store', $purchase), [
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
        ])->assertSessionHasErrors(['amount' => 'Esta compra ya tiene una salida de caja o banco contabilizada. Revisa el comprobante antes de registrar otro pago.']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_purchase_accounted_as_a_payable_can_still_record_a_payment(): void
    {
        $company = $this->authenticateWithCompany();
        $payable = ChartOfAccount::create([
            'code' => '220501',
            'name' => 'Proveedores de prueba',
            'class' => 2,
            'nature' => 'credit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $voucher = AccountingVoucher::create([
            'voucher_type' => 'compra',
            'consecutive' => 'COMP-CREDITO',
            'voucher_date' => now()->toDateString(),
            'total_debit' => 500,
            'total_credit' => 500,
        ]);
        $voucher->lines()->create([
            'chart_of_account_id' => $payable->id,
            'debit' => 0,
            'credit' => 500,
        ]);
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-CREDITO',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => now()->toDateString(),
            'total_pagar' => 500,
            'accounting_voucher_id' => $voucher->id,
        ]);
        $paymentDetails = $this->paymentDetails($company, 'PAY2205');

        $this->post(route('purchases.payments.store', $purchase), [
            'amount' => 100,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
        ])->assertSessionHasNoErrors();

        $this->assertSame(100.0, $purchase->paidAmount());

        $payment = $purchase->payments()->firstOrFail();
        $this->get(route('purchases.statement', $purchase))
            ->assertOk()
            ->assertSee('Caja de prueba')
            ->assertSee($payment->accountingVoucher->consecutive);
    }

    public function test_unique_exact_bank_match_links_one_receipt_and_balanced_voucher(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-BANK-1',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'total' => 400,
        ]);
        $details = $this->paymentDetails($company, 'PAY1305', false, 'CORR-001');
        $bankStatement = BankStatement::create([
            'company_id' => $company->id,
            'payment_method_id' => $details['payment_method_id'],
            'transaction_date' => now()->toDateString(),
            'reference' => 'CORR-001',
            'transaction_type' => 'credit',
            'amount' => 400,
            'status' => 'pending',
        ]);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 400,
            'payment_date' => now()->toDateString(),
            ...$details,
        ])->assertRedirect()->assertSessionHas('success', fn (string $message) => str_contains($message, 'conciliado automáticamente'));

        $payment = $sale->payments()->firstOrFail();
        $this->assertDatabaseHas('bank_statements', [
            'id' => $bankStatement->id,
            'status' => 'reconciled',
            'matched_type' => 'payment',
            'matched_id' => $payment->id,
            'accounting_voucher_id' => $payment->accounting_voucher_id,
        ]);
        $this->assertDatabaseHas('accounting_vouchers', [
            'id' => $payment->accounting_voucher_id,
            'voucher_type' => 'recibo_caja',
            'total_debit' => 400,
            'total_credit' => 400,
        ]);
    }

    public function test_bank_import_after_payment_automatically_reconciles_only_one_exact_match(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-BANK-2',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'total' => 250,
        ]);
        $details = $this->paymentDetails($company, 'PAY1305', false, 'CORR-002');
        $this->post(route('sales.payments.store', $sale), [
            'amount' => 250,
            'payment_date' => now()->toDateString(),
            ...$details,
        ])->assertRedirect();

        $csv = "fecha;referencia;descripcion;tipo;importe\n".now()->toDateString().";CORR-002;Consignación corresponsal;credit;250\n";
        $this->post(route('bank-reconciliation.import'), [
            'file' => UploadedFile::fake()->createWithContent('extracto.csv', $csv),
            'payment_method_id' => $details['payment_method_id'],
        ])->assertRedirect();

        $this->assertDatabaseHas('bank_statements', [
            'payment_method_id' => $details['payment_method_id'],
            'reference' => 'CORR-002',
            'status' => 'reconciled',
            'matched_type' => 'payment',
        ]);
    }

    public function test_duplicate_bank_statement_matches_remain_pending_instead_of_reconciling_ambiguously(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-BANK-3',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'total' => 300,
        ]);
        $details = $this->paymentDetails($company, 'PAY1305', false, 'CORR-DUP');
        foreach (range(1, 2) as $index) {
            BankStatement::create([
                'company_id' => $company->id,
                'payment_method_id' => $details['payment_method_id'],
                'transaction_date' => now()->toDateString(),
                'reference' => 'CORR-DUP',
                'transaction_type' => 'credit',
                'amount' => 300,
                'status' => 'pending',
            ]);
        }

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 300,
            'payment_date' => now()->toDateString(),
            ...$details,
        ])->assertRedirect()->assertSessionHas('success', fn (string $message) => str_contains($message, 'pendiente de conciliación'));

        $this->assertDatabaseCount('bank_statements', 2);
        $this->assertDatabaseHas('bank_statements', ['reference' => 'CORR-DUP', 'status' => 'pending']);
    }

    public function test_a_saved_credit_note_reduces_the_payment_limit_even_while_it_is_a_draft(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-PAY-NC',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 1000,
        ]);
        $paymentDetails = $this->paymentDetails($company, 'PAY1305');
        CommercialDocument::create([
            'company_id' => $company->id,
            'document_type' => 'customer_credit_note',
            'consecutive' => 'NC-PAY-1',
            'status' => 'draft',
            'third_party_name' => 'Cliente de prueba',
            'document_date' => now()->toDateString(),
            'reference_sale_id' => $sale->id,
            'total' => 300,
        ]);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 701,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
        ])->assertSessionHasErrors('amount');

        $this->assertSame(700.0, $sale->fresh()->balanceDue((float) $sale->total));
        $this->assertSame(0.0, $sale->fresh()->paidAmount());
    }

    public function test_fully_paying_a_document_marks_it_as_paid_in_the_statement(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-PAY-2',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 200,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 200,
        ]);
        $paymentDetails = $this->paymentDetails($company, 'PAY1305');

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 200,
            'payment_date' => now()->toDateString(),
            ...$paymentDetails,
        ])->assertRedirect();

        $this->get(route('sales.statement', $sale))->assertOk()->assertSee('Pagada');
    }

    public function test_receipt_payment_is_rejected_when_the_income_period_is_closed(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-CLOSED-1',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => '2026-10-15',
            'subtotal' => 1000,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 1000,
        ]);
        $this->closeOctober($company);
        $paymentDetails = $this->paymentDetails($company, 'PAY1305');

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 100,
            'payment_date' => '2026-10-15',
            ...$paymentDetails,
        ])->assertStatus(409);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1000.0, $sale->fresh()->balanceDue((float) $sale->total));
    }

    public function test_supplier_payment_is_rejected_when_the_expense_period_is_closed(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $company = $this->authenticateWithCompany();
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-CLOSED-1',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => '2026-10-15',
            'subtotal' => 500,
            'iva_total' => 0,
            'retefuente' => 0,
            'total_pagar' => 500,
        ]);
        $this->closeOctober($company);
        $paymentDetails = $this->paymentDetails($company, 'PAY2205');

        $this->post(route('purchases.payments.store', $purchase), [
            'amount' => 100,
            'payment_date' => '2026-10-15',
            ...$paymentDetails,
        ])->assertStatus(409);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(500.0, $purchase->fresh()->balanceDue((float) $purchase->total_pagar));
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function closeOctober(Company $company): void
    {
        AccountingPeriod::create([
            'company_id' => $company->id,
            'period_type' => 'month',
            'period_key' => '2026-10',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'closed',
            'locked_modules' => ['purchases', 'income', 'expenses', 'bank'],
            'closed_at' => now(),
        ]);
    }

    private function paymentDetails(Company $company, string $counterCode, bool $isCash = true, ?string $reference = null): array
    {
        $paymentAccount = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => $isCash ? '110501' : '111005',
            'name' => 'Caja o banco de prueba',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $counterAccount = ChartOfAccount::query()->firstOrCreate(
            [
                'company_id' => $company->id,
                'code' => match ($counterCode) {
                    'PAY1305' => '130501',
                    'PAY2205' => '220501',
                    default => $counterCode,
                },
            ],
            [
                'name' => 'Contrapartida de prueba',
                'class' => 2,
                'nature' => 'credit',
                'allows_posting' => true,
                'active' => true,
            ],
        );
        $method = PaymentMethod::create([
            'company_id' => $company->id,
            'name' => $isCash ? 'Caja de prueba' : 'Banco de prueba',
            'chart_of_account_id' => $paymentAccount->id,
            'is_cash' => $isCash,
            'bank_name' => $isCash ? null : 'Banco de prueba',
            'account_number' => $isCash ? null : '987654321',
            'is_editable' => true,
        ]);

        return [
            'payment_method_id' => $method->id,
            'counter_account_id' => $counterAccount->id,
            'reference' => $reference,
        ];
    }
}
