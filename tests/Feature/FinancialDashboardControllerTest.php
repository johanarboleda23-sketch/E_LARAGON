<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\AccountingVoucherLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialDashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('financial-dashboard.index'))->assertRedirect(route('login'));
    }

    public function test_it_computes_gross_profit_from_sales_and_the_items_average_cost(): void
    {
        $company = $this->authenticateWithCompany();
        $item = Item::create([
            'company_id' => $company->id,
            'type' => 'producto',
            'name' => 'Producto de prueba',
            'sale_price' => 100,
            'purchase_price' => 60,
            'average_cost' => 60,
            'stock' => 50,
            'min_stock' => 0,
        ]);
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-1',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 1000,
        ]);
        SaleDetail::create([
            'company_id' => $company->id,
            'sale_id' => $sale->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_price' => 100,
            'iva_percentage' => 0,
            'discount_percentage' => 0,
            'line_total' => 1000,
        ]);

        // COGS = 10 * 60 = 600; utilidad bruta = 1000 - 600 = 400; margen = 40%
        $response = $this->get(route('financial-dashboard.index'))->assertOk();
        $response->assertSee('40%');
    }

    public function test_it_sums_class_5_accounting_lines_as_operating_expenses(): void
    {
        $company = $this->authenticateWithCompany();
        $account = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '5105-TEST',
            'name' => 'Gasto de prueba',
            'class' => 5,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'consecutive' => 'EG-1',
            'voucher_type' => 'egreso',
            'voucher_date' => now()->toDateString(),
            'description' => 'Gasto de prueba',
            'total_debit' => 500,
            'total_credit' => 500,
        ]);
        AccountingVoucherLine::create([
            'accounting_voucher_id' => $voucher->id,
            'chart_of_account_id' => $account->id,
            'detail' => 'Gasto',
            'debit' => 500,
            'credit' => 0,
        ]);

        $this->get(route('financial-dashboard.index'))->assertOk()->assertSee('500');
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
