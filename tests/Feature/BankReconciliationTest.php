<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\AccountingVoucherLine;
use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $this->post(route('bank-reconciliation.reconcile', $statement), [
            'matched_type' => 'manual',
            'notes' => 'Revisado contra extracto bancario.',
        ])->assertRedirect();

        $this->assertSame('reconciled', $statement->fresh()->status);
        $this->assertNotNull($statement->fresh()->reconciled_at);
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
