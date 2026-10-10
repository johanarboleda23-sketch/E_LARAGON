<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxDraftControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tax-drafts.index'))->assertRedirect(route('login'));
    }

    public function test_iva_draft_computes_the_balance_between_generated_and_deductible_vat(): void
    {
        $this->authenticateWithCompany();

        Sale::create([
            'invoice_number' => 'FV-1',
            'customer_name' => 'Cliente uno',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000000,
            'iva_total' => 190000,
            'total' => 1190000,
        ]);
        Purchase::create([
            'invoice_number' => 'FC-1',
            'provider' => 'Proveedor uno',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 400000,
            'iva_total' => 76000,
            'total_pagar' => 476000,
        ]);

        $response = $this->get(route('tax-drafts.show', 'iva'));

        $response->assertOk();
        $response->assertSee(number_format(190000, 2));
        $response->assertSee(number_format(76000, 2));
        $response->assertSee(number_format(114000, 2));
    }

    public function test_retention_draft_sums_withheld_amounts_from_purchases_and_support_documents(): void
    {
        $company = $this->authenticateWithCompany();

        Purchase::create([
            'invoice_number' => 'FC-1',
            'provider' => 'Proveedor uno',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 1000000,
            'retefuente' => 25000,
            'total_pagar' => 975000,
        ]);
        SupportDocument::create([
            'company_id' => $company->id,
            'third_party_id' => ThirdParty::create(['company_id' => $company->id, 'name' => 'Contratista', 'is_supplier' => true])->id,
            'consecutive' => 'DS-1',
            'document_date' => now()->toDateString(),
            'concept' => 'Honorarios',
            'subtotal' => 500000,
            'retention_total' => 55000,
            'total' => 445000,
        ]);

        $response = $this->get(route('tax-drafts.show', 'retencion'));

        $response->assertOk();
        $response->assertSee(number_format(25000, 2));
        $response->assertSee(number_format(55000, 2));
        $response->assertSee(number_format(80000, 2));
    }

    public function test_ica_draft_uses_the_configured_rate(): void
    {
        $company = $this->authenticateWithCompany();
        $company->update(['ica_rate_per_thousand' => 6.9]);

        Sale::create([
            'invoice_number' => 'FV-1',
            'customer_name' => 'Cliente uno',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000000,
            'iva_total' => 190000,
            'total' => 1190000,
        ]);

        $response = $this->get(route('tax-drafts.show', 'ica'));

        $response->assertOk();
        $response->assertSee(number_format(1000000 * 6.9 / 1000, 2));
    }

    public function test_income_tax_draft_subtracts_costs_and_class_five_expenses(): void
    {
        $company = $this->authenticateWithCompany();
        $company->update(['income_tax_rate_percentage' => 35]);

        Sale::create([
            'invoice_number' => 'FV-1',
            'customer_name' => 'Cliente uno',
            'sale_date' => now()->toDateString(),
            'subtotal' => 2000000,
            'iva_total' => 380000,
            'total' => 2380000,
        ]);
        Purchase::create([
            'invoice_number' => 'FC-1',
            'provider' => 'Proveedor uno',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 500000,
            'iva_total' => 95000,
            'total_pagar' => 595000,
        ]);
        $account = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '513505',
            'name' => 'Arrendamientos',
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
            'third_party' => 'Arrendador',
            'total_debit' => 200000,
            'total_credit' => 200000,
        ]);
        $voucher->lines()->create([
            'company_id' => $company->id,
            'chart_of_account_id' => $account->id,
            'detail' => 'Arriendo',
            'debit' => 200000,
            'credit' => 0,
        ]);

        // Utilidad = 2,000,000 - 500,000 - 200,000 = 1,300,000; impuesto = 35% => 455,000
        $response = $this->get(route('tax-drafts.show', 'renta'));

        $response->assertOk();
        $response->assertSee(number_format(1300000, 2));
        $response->assertSee(number_format(455000, 2));
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
