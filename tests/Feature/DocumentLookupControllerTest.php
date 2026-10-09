<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\Company;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentLookupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_the_voucher_linked_to_a_sale_consecutive(): void
    {
        $company = $this->authenticateWithCompany();
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'consecutive' => 'CC-1',
            'voucher_type' => 'sale',
            'voucher_date' => now()->toDateString(),
            'description' => 'Venta',
            'total_debit' => 100,
            'total_credit' => 100,
        ]);
        Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FAC-001',
            'sale_date' => now()->toDateString(),
            'customer_name' => 'Cliente de prueba',
            'subtotal' => 100,
            'iva_total' => 0,
            'total' => 100,
            'accounting_voucher_id' => $voucher->id,
        ]);

        $response = $this->getJson(route('documents.lookup', ['module' => 'sale', 'consecutive' => 'FAC-001']));

        $response->assertOk()->assertJson(['found' => true, 'voucher_id' => $voucher->id]);
    }

    public function test_it_returns_not_found_for_an_unknown_consecutive(): void
    {
        $this->authenticateWithCompany();

        $response = $this->getJson(route('documents.lookup', ['module' => 'sale', 'consecutive' => 'NOPE']));

        $response->assertNotFound()->assertJson(['found' => false]);
    }

    public function test_it_returns_the_document_url_when_it_exists_but_has_no_voucher_yet(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FAC-002',
            'sale_date' => now()->toDateString(),
            'customer_name' => 'Cliente de prueba',
            'subtotal' => 100,
            'iva_total' => 0,
            'total' => 100,
        ]);

        $response = $this->getJson(route('documents.lookup', ['module' => 'sale', 'consecutive' => 'FAC-002']));

        $response->assertNotFound()
            ->assertJson([
                'found' => false,
                'document_url' => route('sales.show', $sale),
            ]);
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
