<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountingVoucher;
use App\Models\CommercialDocument;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\PayrollRun;
use App\Models\Sale;
use App\Models\ThirdParty;
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

    public function test_it_finds_each_type_of_note_without_crossing_types_or_companies(): void
    {
        $company = $this->authenticateWithCompany();
        foreach ([
            'customer_credit_note' => 'commercial-documents.customer-credit-notes',
            'customer_debit_note' => 'commercial-documents.customer-debit-notes',
            'supplier_credit_note' => 'commercial-documents.supplier-credit-notes',
            'supplier_debit_note' => 'commercial-documents.supplier-debit-notes',
        ] as $type => $routeName) {
            $document = CommercialDocument::factory()->create(['company_id' => $company->id, 'document_type' => $type]);
            $this->getJson(route('documents.lookup', ['module' => $type, 'consecutive' => $document->consecutive]))
                ->assertOk()->assertJsonPath('document_url', route($routeName, ['preview' => $document->id]));
            $this->getJson(route('documents.lookup', ['module' => 'quotation', 'consecutive' => $document->consecutive]))->assertUnprocessable();
        }
        $otherCompany = Company::factory()->create();
        $foreign = CommercialDocument::factory()->create(['company_id' => $otherCompany->id, 'document_type' => 'customer_credit_note']);
        $this->getJson(route('documents.lookup', ['module' => 'customer_credit_note', 'consecutive' => $foreign->consecutive]))->assertNotFound();
    }

    public function test_it_filters_egresos_and_receipts_by_voucher_type(): void
    {
        $company = $this->authenticateWithCompany();
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id, 'consecutive' => 'EG-123', 'voucher_type' => 'egreso',
            'voucher_date' => now()->toDateString(), 'total_debit' => 100, 'total_credit' => 100,
        ]);

        $this->getJson(route('documents.lookup', ['module' => 'egreso', 'consecutive' => 'EG-123']))
            ->assertOk()->assertJsonPath('voucher_id', $voucher->id);
        $this->getJson(route('documents.lookup', ['module' => 'recibo_caja', 'consecutive' => 'EG-123']))->assertNotFound();
    }

    public function test_it_finds_a_third_party_by_document_and_a_closed_period_by_key(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create(['company_id' => $company->id, 'name' => 'Tercero', 'document' => '900123456']);
        AccountingPeriod::create([
            'company_id' => $company->id, 'period_type' => 'month', 'period_key' => '2025-12',
            'period_start' => '2025-12-01', 'period_end' => '2025-12-31', 'status' => 'closed', 'locked_modules' => [],
        ]);
        $this->getJson(route('documents.lookup', ['module' => 'third_party', 'consecutive' => '900123456']))
            ->assertOk()->assertJsonPath('document_url', route('third-parties.show', $thirdParty));
        $this->getJson(route('documents.lookup', ['module' => 'closing', 'consecutive' => '2025-12']))
            ->assertOk()->assertJsonPath('document_url', route('period-closing.index', ['year' => '2025']));
    }

    public function test_pos_search_accepts_pos_consecutives_and_not_regular_sales(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create(['company_id' => $company->id, 'invoice_number' => 'POS-123', 'sale_date' => now()->toDateString(), 'customer_name' => 'Cliente', 'subtotal' => 100, 'iva_total' => 0, 'total' => 100]);
        $this->getJson(route('documents.lookup', ['module' => 'pos', 'consecutive' => 'POS-123']))
            ->assertNotFound()->assertJsonPath('document_url', route('sales.show', $sale));
        $this->getJson(route('documents.lookup', ['module' => 'pos', 'consecutive' => 'FAC-123']))->assertNotFound();
    }

    public function test_pos_search_finds_sales_with_an_authorized_consecutive(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create(['company_id' => $company->id, 'invoice_number' => 'FV-789', 'sale_date' => now()->toDateString(), 'customer_name' => 'Cliente', 'subtotal' => 100, 'iva_total' => 0, 'total' => 100]);
        $item = Item::factory()->create(['company_id' => $company->id]);
        InventoryMovement::create(['company_id' => $company->id, 'item_id' => $item->id, 'type' => 'salida', 'quantity' => 1, 'stock_before' => 1, 'stock_after' => 0, 'reason' => 'Venta POS FV-789']);

        $this->getJson(route('documents.lookup', ['module' => 'pos', 'consecutive' => 'FV-789']))
            ->assertNotFound()->assertJsonPath('document_url', route('sales.show', $sale));
    }

    public function test_social_security_search_finds_a_voucher_or_payroll_period(): void
    {
        $company = $this->authenticateWithCompany();
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id, 'consecutive' => 'SS-123', 'voucher_type' => 'seguridad_social',
            'voucher_date' => now()->toDateString(), 'total_debit' => 100, 'total_credit' => 100,
        ]);
        $run = PayrollRun::create(['company_id' => $company->id, 'period' => '2025-12', 'payment_date' => '2025-12-31', 'status' => 'calculated']);

        $this->getJson(route('documents.lookup', ['module' => 'social_security', 'consecutive' => 'SS-123']))
            ->assertOk()->assertJsonPath('voucher_id', $voucher->id);
        $this->getJson(route('documents.lookup', ['module' => 'social_security', 'consecutive' => '2025-12']))
            ->assertOk()->assertJsonPath('document_url', route('payroll.show', $run));
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
