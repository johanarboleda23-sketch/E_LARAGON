<?php

namespace Tests\Feature;

use App\Mail\CommercialDocumentMail;
use App\Models\CommercialDocument;
use App\Models\Company;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommercialDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('commercial-documents.quotations'))->assertRedirect(route('login'));
    }

    public function test_dashboard_displays_both_note_modules(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Nota crédito clientes')
            ->assertSeeText('Nota débito proveedores');
    }

    public function test_each_document_module_renders_its_own_heading(): void
    {
        $this->authenticateWithCompany();

        foreach ([
            'commercial-documents.quotations' => 'Cotización',
            'commercial-documents.sales-orders' => 'Orden de venta',
            'commercial-documents.remissions' => 'Remisión',
            'commercial-documents.purchase-orders' => 'Orden de compra',
            'commercial-documents.customer-credit-notes' => 'Nota crédito clientes',
            'commercial-documents.supplier-debit-notes' => 'Nota débito proveedores',
        ] as $routeName => $heading) {
            $this->get(route($routeName))->assertOk()->assertSeeText($heading);
        }
    }

    public function test_saving_a_quotation_keeps_the_stock_unchanged(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();

        $response = $this->post(route('commercial-documents.quotations.store'), $this->validPayload('quotation', $item));

        $document = CommercialDocument::query()->firstOrFail();
        $response->assertRedirect(route('commercial-documents.quotations', ['document' => $document->id]));
        $this->assertSame('draft', $document->status);
        $this->assertSame(10, $item->fresh()->stock);
        $this->assertDatabaseCount('commercial_document_lines', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_converting_a_sales_document_creates_one_invoice_and_deducts_stock_once(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $document = $this->createDraft('sales_order', $item);

        $this->post(route('commercial-documents.convert', $document))->assertRedirect(route('commercial-documents.sales-orders'));
        $this->assertSame(8, $item->fresh()->stock);
        $this->assertSame('converted', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->converted_sale_id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->post(route('commercial-documents.convert', $document))->assertStatus(409);
        $this->assertSame(8, $item->fresh()->stock);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_insufficient_stock_rolls_back_the_sales_conversion(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $item->update(['stock' => 1]);
        $document = $this->createDraft('sales_order', $item);

        $this->from(route('commercial-documents.sales-orders'))
            ->post(route('commercial-documents.convert', $document))
            ->assertRedirect(route('commercial-documents.sales-orders'))
            ->assertSessionHasErrors('items');

        $this->assertSame(1, $item->fresh()->stock);
        $this->assertSame('draft', $document->fresh()->status);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_document_from_another_company_is_not_visible(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $document = CommercialDocument::create([
            'company_id' => $otherCompany->id,
            'document_type' => 'quotation',
            'consecutive' => 'COT-OTRA-EMPRESA',
            'status' => 'draft',
            'third_party_name' => 'Cliente privado',
            'document_date' => '2026-09-26',
        ]);

        $this->get(route('commercial-documents.print', $document))->assertNotFound();
    }

    public function test_converting_a_purchase_order_registers_the_purchase_and_adds_stock_once(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $document = $this->createDraft('purchase_order', $item);

        $this->post(route('commercial-documents.convert', $document))->assertRedirect(route('commercial-documents.purchase-orders'));

        $this->assertSame(12, $item->fresh()->stock);
        $this->assertSame('converted', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->converted_purchase_id);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_sales_order_consecutive_cannot_be_changed_after_saving(): void
    {
        $this->authenticateWithCompany();
        $document = $this->createDraft('sales_order', $this->createItem());

        $this->patch(route('commercial-documents.consecutive', $document), ['consecutive' => 'OV-EDITADA'])
            ->assertForbidden();
    }

    public function test_editable_document_consecutive_can_be_changed(): void
    {
        $this->authenticateWithCompany();
        $document = $this->createDraft('quotation', $this->createItem());

        $this->patch(route('commercial-documents.consecutive', $document), ['consecutive' => 'COT-MANUAL-25'])
            ->assertRedirect(route('commercial-documents.quotations'));

        $this->assertSame('COT-MANUAL-25', $document->fresh()->consecutive);
    }

    public function test_document_can_be_emailed_printed_and_downloaded(): void
    {
        $this->authenticateWithCompany();
        Mail::fake();
        $document = $this->createDraft('quotation', $this->createItem());

        $this->post(route('commercial-documents.email', $document), ['email' => 'buyer@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Documento enviado por correo.');
        Mail::assertSent(CommercialDocumentMail::class);

        $this->get(route('commercial-documents.print', $document))
            ->assertOk()
            ->assertSeeText('COT-000001')
            ->assertSeeText('Producto de prueba');

        $this->get(route('commercial-documents.download', $document))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSeeText('Producto de prueba');
    }

    public function test_customer_credit_note_requires_and_saves_its_source_sale(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $payload = $this->validPayload('customer_credit_note', $item);

        $this->post(route('commercial-documents.customer-credit-notes.store'), $payload)
            ->assertSessionHasNoErrors();

        $note = CommercialDocument::query()->where('document_type', 'customer_credit_note')->firstOrFail();
        $this->assertSame('FAC-CLIENTE-001', $note->referenceSale->invoice_number);
        $this->assertNull($note->reference_purchase_id);
        $this->assertSame('not_configured', $note->dian_status);
    }

    public function test_customer_credit_note_cannot_reference_another_customers_sale(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $payload = $this->validPayload('customer_credit_note', $item);
        $otherCustomer = ThirdParty::create([
            'type' => 'natural',
            'name' => 'Otro cliente',
            'document' => '800654321',
            'is_customer' => true,
            'active' => true,
        ]);
        $payload['third_party_id'] = $otherCustomer->id;

        $this->post(route('commercial-documents.customer-credit-notes.store'), $payload)->assertNotFound();
        $this->assertDatabaseCount('commercial_documents', 0);
    }

    public function test_customer_credit_note_requires_a_source_invoice(): void
    {
        $this->authenticateWithCompany();
        $payload = $this->validPayload('customer_credit_note', $this->createItem());
        unset($payload['reference_invoice_id']);

        $this->from(route('commercial-documents.customer-credit-notes'))
            ->post(route('commercial-documents.customer-credit-notes.store'), $payload)
            ->assertRedirect(route('commercial-documents.customer-credit-notes'))
            ->assertSessionHasErrors('reference_invoice_id');
    }

    public function test_supplier_debit_note_requires_and_saves_its_source_purchase(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $payload = $this->validPayload('supplier_debit_note', $item);

        $this->post(route('commercial-documents.supplier-debit-notes.store'), $payload)
            ->assertSessionHasNoErrors();

        $note = CommercialDocument::query()->where('document_type', 'supplier_debit_note')->firstOrFail();
        $this->assertSame('FAC-PROVEEDOR-001', $note->referencePurchase->invoice_number);
        $this->assertNull($note->reference_sale_id);
    }

    public function test_dian_button_does_not_claim_an_unconfigured_note_was_sent(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createDraft('customer_credit_note', $this->createItem());

        $this->post(route('commercial-documents.dian', $note))
            ->assertRedirect()
            ->assertSessionHas('error', 'La nota no se envió: la integración con la DIAN no está configurada.');

        $this->assertSame('not_configured', $note->fresh()->dian_status);
    }

    public function test_statement_shows_the_original_invoice_and_credit_note(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createDraft('customer_credit_note', $this->createItem());

        $this->get(route('commercial-documents.statement', $note))
            ->assertOk()
            ->assertSeeText('FAC-CLIENTE-001')
            ->assertSeeText($note->consecutive)
            ->assertSeeText('No incluye pagos o abonos');
    }

    public function test_statement_shows_the_original_purchase_and_supplier_debit_note(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createDraft('supplier_debit_note', $this->createItem());

        $this->get(route('commercial-documents.statement', $note))
            ->assertOk()
            ->assertSeeText('FAC-PROVEEDOR-001')
            ->assertSeeText($note->consecutive)
            ->assertSeeText('No incluye pagos o abonos');
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createItem(): Item
    {
        return Item::create([
            'type' => 'producto',
            'name' => 'Producto de prueba',
            'code' => 'SKU-PRUEBA',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 10,
            'min_stock' => 1,
        ]);
    }

    private function createDraft(string $type, Item $item): CommercialDocument
    {
        $routeNames = [
            'quotation' => 'commercial-documents.quotations.store',
            'sales_order' => 'commercial-documents.sales-orders.store',
            'remission' => 'commercial-documents.remissions.store',
            'purchase_order' => 'commercial-documents.purchase-orders.store',
            'customer_credit_note' => 'commercial-documents.customer-credit-notes.store',
            'supplier_debit_note' => 'commercial-documents.supplier-debit-notes.store',
        ];
        $response = $this->post(route($routeNames[$type]), $this->validPayload($type, $item));
        $response->assertSessionHasNoErrors();

        return CommercialDocument::query()->latest('id')->firstOrFail();
    }

    private function validPayload(string $type, Item $item): array
    {
        $isSupplier = in_array($type, ['purchase_order', 'supplier_debit_note'], true);
        $party = ThirdParty::create([
            'type' => 'natural',
            'name' => 'Tercero de prueba',
            'document' => '900123456',
            'email' => 'buyer@example.com',
            'is_customer' => ! $isSupplier,
            'is_supplier' => $isSupplier,
            'active' => true,
        ]);

        $referenceInvoiceId = null;
        if ($type === 'customer_credit_note') {
            $referenceInvoiceId = Sale::create([
                'invoice_number' => 'FAC-CLIENTE-001',
                'customer_name' => $party->name,
                'customer_document' => $party->document,
                'sale_date' => '2026-09-01',
                'subtotal' => 100,
                'iva_total' => 19,
                'discount_total' => 0,
                'withholding_concept' => 'none',
                'retention_base' => 0,
                'retention_total' => 0,
                'total' => 119,
            ])->id;
        } elseif ($type === 'supplier_debit_note') {
            $referenceInvoiceId = Purchase::create([
                'invoice_number' => 'FAC-PROVEEDOR-001',
                'provider' => $party->name,
                'purchase_date' => '2026-09-01',
                'subtotal' => 100,
                'iva_total' => 19,
                'retefuente' => 0,
                'total_pagar' => 119,
            ])->id;
        }

        return [
            'consecutive' => match ($type) {
                'quotation' => 'COT-000001',
                'sales_order' => 'OV-000001',
                'remission' => 'REM-000001',
                'purchase_order' => 'OC-000001',
                'customer_credit_note' => 'NC-000001',
                'supplier_debit_note' => 'ND-000001',
            },
            'third_party_id' => $party->id,
            'reference_invoice_id' => $referenceInvoiceId,
            'recipient_email' => 'buyer@example.com',
            'document_date' => '2026-09-26',
            'due_date' => '2026-10-26',
            'notes' => 'Documento de prueba',
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 2,
                'unit_price' => $type === 'purchase_order' ? 50 : 100,
                'iva_percentage' => 19,
                'discount_percentage' => 0,
                'utility_percentage' => 20,
            ]],
        ];
    }
}
