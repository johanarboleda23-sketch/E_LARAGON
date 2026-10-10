<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BulkOperationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('bulk-operations.index'))->assertRedirect(route('login'));
    }

    public function test_pos_family_only_lists_pos_sales(): void
    {
        $company = $this->authenticateWithCompany();

        $pos = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'POS-123',
            'customer_name' => 'Cliente mostrador',
            'sale_date' => now()->toDateString(),
            'subtotal' => 10000,
            'iva_total' => 1900,
            'total' => 11900,
        ]);

        Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-1',
            'customer_name' => 'Cliente factura',
            'sale_date' => now()->toDateString(),
            'subtotal' => 10000,
            'iva_total' => 1900,
            'total' => 11900,
        ]);

        $response = $this->get(route('bulk-operations.index', ['family' => 'pos']));

        $response->assertOk();
        $response->assertSee('POS-123');
        $response->assertDontSee('FV-1');
    }

    public function test_expense_vouchers_family_can_be_printed_and_downloaded(): void
    {
        $company = $this->authenticateWithCompany();

        $account = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '513505',
            'name' => 'Gastos de arrendamiento',
            'account_type' => 'gasto',
            'class' => '5',
            'nature' => 'debito',
            'allows_posting' => true,
        ]);

        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'consecutive' => 'EG-1',
            'voucher_type' => 'egreso',
            'voucher_date' => now()->toDateString(),
            'third_party' => 'Arrendador S.A.S.',
            'description' => 'Pago de arriendo',
            'total_debit' => 500000,
            'total_credit' => 500000,
        ]);

        $voucher->lines()->create([
            'company_id' => $company->id,
            'chart_of_account_id' => $account->id,
            'detail' => 'Arriendo de bodega',
            'debit' => 500000,
            'credit' => 0,
        ]);

        $this->get(route('bulk-operations.index', ['family' => 'expense_vouchers']))
            ->assertOk()
            ->assertSee('EG-1');

        $this->post(route('bulk-operations.print'), [
            'family' => 'expense_vouchers',
            'ids' => [$voucher->id],
        ])->assertOk();

        $this->post(route('bulk-operations.download'), [
            'family' => 'expense_vouchers',
            'ids' => [$voucher->id],
        ])->assertOk();
    }

    public function test_all_vouchers_family_can_be_emailed_in_bulk(): void
    {
        Mail::fake();

        $company = $this->authenticateWithCompany();

        AccountingVoucher::create([
            'company_id' => $company->id,
            'consecutive' => 'RC-1',
            'voucher_type' => 'recibo_caja',
            'voucher_date' => now()->toDateString(),
            'third_party' => 'Cliente sin correo',
            'description' => 'Recibo de caja',
            'total_debit' => 100000,
            'total_credit' => 100000,
        ]);

        $response = $this->post(route('bulk-operations.email'), [
            'family' => 'all_vouchers',
            'ids' => [AccountingVoucher::first()->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }
}
