<?php

namespace Tests\Feature;

use App\Mail\CommercialDocumentMail;
use App\Models\CommercialDocument;
use App\Models\Company;
use App\Models\FactusCredential;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        $this->assertSame(10.0, $item->fresh()->stock);
        $this->assertDatabaseCount('commercial_document_lines', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->get(route('commercial-documents.quotations', ['document' => $document->id]))
            ->assertOk()
            ->assertSeeText('Cotización · '.$document->consecutive);
    }

    public function test_converting_a_sales_document_creates_one_invoice_and_deducts_stock_once(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $document = $this->createDraft('sales_order', $item);

        $this->post(route('commercial-documents.convert', $document))->assertRedirect(route('commercial-documents.sales-orders'));
        $this->assertSame(8.0, $item->fresh()->stock);
        $this->assertSame('converted', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->converted_sale_id);
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->post(route('commercial-documents.convert', $document))->assertStatus(409);
        $this->assertSame(8.0, $item->fresh()->stock);
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

        $this->assertSame(1.0, $item->fresh()->stock);
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

        $this->assertSame(12.0, $item->fresh()->stock);
        $this->assertSame('converted', $document->fresh()->status);
        $this->assertNotNull($document->fresh()->converted_purchase_id);
        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_converting_a_sales_order_applies_its_unit_conversion_to_stock(): void
    {
        $company = $this->authenticateWithCompany();
        $item = $this->createItem();
        $item->update(['stock' => 20]);
        $conversion = $item->unitConversions()->create([
            'company_id' => $company->id,
            'name' => 'caja',
            'factor' => 6,
        ]);
        $document = $this->createDraft('sales_order', $item);
        $document->lines()->update([
            'unit_conversion_id' => $conversion->id,
            'unit_name' => 'caja',
            'unit_factor' => 6,
            'quantity' => 0.5,
        ]);

        $this->post(route('commercial-documents.convert', $document))
            ->assertRedirect(route('commercial-documents.sales-orders'));

        $detail = Sale::query()->findOrFail($document->fresh()->converted_sale_id)->details()->firstOrFail();
        $this->assertSame(17.0, $item->fresh()->stock);
        $this->assertSame('caja', $detail->unit_name);
        $this->assertSame(6.0, (float) $detail->unit_factor);
        $this->assertSame(0.5, $detail->quantity);
    }

    public function test_converting_a_purchase_order_applies_its_unit_conversion_to_stock(): void
    {
        $company = $this->authenticateWithCompany();
        $item = $this->createItem();
        $conversion = $item->unitConversions()->create([
            'company_id' => $company->id,
            'name' => 'paquete',
            'factor' => 3,
        ]);
        $document = $this->createDraft('purchase_order', $item);
        $document->lines()->update([
            'unit_conversion_id' => $conversion->id,
            'unit_name' => 'paquete',
            'unit_factor' => 3,
        ]);

        $this->post(route('commercial-documents.convert', $document))
            ->assertRedirect(route('commercial-documents.purchase-orders'));

        $detail = Purchase::query()->findOrFail($document->fresh()->converted_purchase_id)->details()->firstOrFail();
        $this->assertSame(16.0, $item->fresh()->stock);
        $this->assertSame('paquete', $detail->unit_name);
        $this->assertSame(3.0, (float) $detail->unit_factor);
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
        $this->assertSame('1', $note->correction_concept_code);
    }

    public function test_saving_a_customer_credit_note_copies_payment_method_applies_retention_and_reduces_balance(): void
    {
        $company = $this->authenticateWithCompany();
        $item = $this->createItem();
        $payload = $this->validPayload('customer_credit_note', $item);
        $sale = Sale::query()->findOrFail($payload['reference_invoice_id']);
        $paymentMethod = PaymentMethod::create([
            'name' => 'Transferencia',
            'is_editable' => true,
        ]);
        $sale->update([
            'payment_method_id' => $paymentMethod->id,
            'withholding_concept' => 'professional_fee_10',
        ]);
        $payload['payment_method_id'] = $paymentMethod->id;
        $payload['withholding_concept'] = 'professional_fee_10';

        $this->post(route('commercial-documents.customer-credit-notes.store'), $payload)
            ->assertSessionHasNoErrors();

        $note = CommercialDocument::query()->where('document_type', 'customer_credit_note')->firstOrFail();
        $this->assertSame($paymentMethod->id, $note->payment_method_id);
        $this->assertSame(100.0, (float) $note->retention_base);
        $this->assertSame(10.0, (float) $note->retention_total);
        $this->assertSame(109.0, (float) $note->total);
        $this->assertSame(10.0, $sale->fresh()->balanceDue((float) $sale->total));
        $this->assertSame($company->id, $note->company_id);
    }

    public function test_customer_credit_notes_cannot_exceed_the_source_sale_total(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $firstPayload = $this->validPayload('customer_credit_note', $item);
        $this->post(route('commercial-documents.customer-credit-notes.store'), $firstPayload)
            ->assertSessionHasNoErrors();

        $secondPayload = $firstPayload;
        $secondPayload['consecutive'] = 'NC-000002';
        $this->from(route('commercial-documents.customer-credit-notes'))
            ->post(route('commercial-documents.customer-credit-notes.store'), $secondPayload)
            ->assertRedirect(route('commercial-documents.customer-credit-notes'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('commercial_documents', 1);
    }

    public function test_sales_invoice_can_start_a_prefilled_credit_note(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $payload = $this->validPayload('customer_credit_note', $item);
        $sale = Sale::query()->findOrFail($payload['reference_invoice_id']);
        $paymentMethod = PaymentMethod::create([
            'name' => 'Transferencia',
            'is_editable' => true,
        ]);
        $sale->update([
            'payment_method_id' => $paymentMethod->id,
            'withholding_concept' => 'professional_fee_10',
        ]);
        $sale->details()->create([
            'item_id' => $item->id,
            'quantity' => 0.5,
            'unit_price' => 100,
            'iva_percentage' => 19,
            'discount_percentage' => 0,
            'line_total' => 59.5,
        ]);

        $this->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee(route('commercial-documents.customer-credit-notes', ['reference_invoice_id' => $sale->id]));

        $this->get(route('commercial-documents.customer-credit-notes', ['reference_invoice_id' => $sale->id]))
            ->assertOk()
            ->assertSee('value="'.$sale->id.'" data-party="Tercero de prueba" selected', false)
            ->assertSee('value="'.$payload['third_party_id'].'" data-email="buyer@example.com" data-document="900123456" data-name="Tercero de prueba" selected', false)
            ->assertSee('name="payment_method_id"', false)
            ->assertSee('value="'.$paymentMethod->id.'" selected', false)
            ->assertSee('value="professional_fee_10" data-rate="0.1" data-base-uvt="0" data-base-on="subtotal" selected', false)
            ->assertSee('value="'.$item->id.'" data-sale-price="100" data-purchase-price="50" selected', false)
            ->assertSee('name="items[0][quantity]" type="number" min="0.0001" step="0.0001" max="1000000" value="0.5"', false);

        $payload['payment_method_id'] = $paymentMethod->id;
        $payload['withholding_concept'] = 'professional_fee_10';
        $payload['items'][0]['quantity'] = 0.5;
        $this->post(route('commercial-documents.customer-credit-notes.store'), $payload)
            ->assertSessionHasNoErrors();

        $note = CommercialDocument::query()->where('document_type', 'customer_credit_note')->firstOrFail();
        $this->assertSame(0.5, (float) $note->lines()->firstOrFail()->quantity);
        $this->assertSame(5.0, (float) $note->retention_total);
    }

    public function test_sales_invoice_without_a_registered_customer_still_opens_credit_note_form(): void
    {
        $this->authenticateWithCompany();
        $sale = Sale::create([
            'invoice_number' => 'FV-SIN-TERCERO',
            'customer_name' => 'Cliente histórico',
            'customer_email' => 'historico@example.com',
            'sale_date' => '2026-09-01',
            'subtotal' => 100,
            'iva_total' => 0,
            'discount_total' => 0,
            'withholding_concept' => 'none',
            'retention_base' => 0,
            'retention_total' => 0,
            'total' => 100,
        ]);

        $this->get(route('commercial-documents.customer-credit-notes', ['reference_invoice_id' => $sale->id]))
            ->assertOk()
            ->assertSee('FV-SIN-TERCERO')
            ->assertSee('historico@example.com');
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

    public function test_purchase_invoice_can_start_a_prefilled_debit_note(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createItem();
        $payload = $this->validPayload('supplier_debit_note', $item);
        $purchase = Purchase::query()->findOrFail($payload['reference_invoice_id']);
        $purchase->details()->create([
            'item_id' => $item->id,
            'quantity' => 2,
            'cost_price' => 50,
            'iva_percentage' => 19,
            'iva_value' => 9.5,
            'utility_percentage' => 20,
            'calculated_sale_price' => 60,
        ]);

        $this->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee(route('commercial-documents.supplier-debit-notes', ['reference_invoice_id' => $purchase->id]));

        $this->get(route('commercial-documents.supplier-debit-notes', ['reference_invoice_id' => $purchase->id]))
            ->assertOk()
            ->assertSee('value="'.$purchase->id.'" data-party="Tercero de prueba" selected', false)
            ->assertSee('value="'.$payload['third_party_id'].'" data-email="buyer@example.com" data-document="900123456" data-name="Tercero de prueba" selected', false)
            ->assertSee('value="'.$item->id.'" data-sale-price="100" data-purchase-price="50" selected', false)
            ->assertSee('name="items[0][quantity]" type="number" min="0.0001" step="0.0001" max="1000000" value="2"', false)
            ->assertSee('name="items[0][unit_price]" type="number" min="0" max="999999999" step="0.01" value="50"', false);
    }

    public function test_dian_button_does_not_claim_an_unconfigured_note_was_sent(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createDraft('customer_credit_note', $this->createItem());

        $this->post(route('commercial-documents.dian', $note))
            ->assertRedirect()
            ->assertSessionHas('error', 'La nota no se envió: Configura las credenciales de Factus en Administración para enviar notas crédito a la DIAN.');

        $this->assertSame('not_configured', $note->fresh()->dian_status);
    }

    public function test_customer_credit_note_requires_an_electronically_issued_source_invoice(): void
    {
        $company = $this->authenticateWithCompany();
        $note = $this->createDraft('customer_credit_note', $this->createItem());
        FactusCredential::create([
            'company_id' => $company->id,
            'environment' => 'sandbox',
            'client_id' => 'client-123',
            'client_secret' => 'secret-123',
            'username' => 'empresa@example.com',
            'password' => 'super-secret',
            'active' => true,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake();

        $this->post(route('commercial-documents.dian', $note))
            ->assertRedirect()
            ->assertSessionHas('error', 'La nota no se envió: La factura origen debe estar emitida electrónicamente por Factus antes de crear su nota crédito.');

        Http::assertNothingSent();
        $this->assertSame('not_configured', $note->fresh()->dian_status);
    }

    public function test_customer_credit_note_is_sent_to_factus_and_records_the_dian_response(): void
    {
        $company = $this->authenticateWithCompany();
        $note = $this->createDraft('customer_credit_note', $this->createItem());
        $note->referenceSale->update([
            'factus_status' => 'enviada',
            'factus_number' => 'SETT-90001',
        ]);
        FactusCredential::create([
            'company_id' => $company->id,
            'environment' => 'sandbox',
            'client_id' => 'client-123',
            'client_secret' => 'secret-123',
            'username' => 'empresa@example.com',
            'password' => 'super-secret',
            'credit_note_numbering_range_id' => 28,
            'active' => true,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake([
            'api-sandbox.factus.com.co/oauth/token' => Http::response(['access_token' => 'token-123'], 200),
            'api-sandbox.factus.com.co/v2/credit-notes/validate' => Http::response([
                'data' => ['credit_note' => ['number' => 'NC-FACTUS-1', 'cude' => 'cude-abc']],
            ], 200),
        ]);

        $this->post(route('commercial-documents.dian', $note))
            ->assertRedirect()
            ->assertSessionHas('success', 'Nota crédito enviada y validada por la DIAN.');

        $note->refresh();
        $this->assertSame('sent', $note->dian_status);
        $this->assertSame('NC-FACTUS-1', $note->dian_number);
        $this->assertSame('cude-abc', $note->dian_cude);
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://api-sandbox.factus.com.co/v2/credit-notes/validate'
            && $request->data()['bill_number'] === 'SETT-90001'
            && $request->data()['correction_concept_code'] === '1'
            && $request->data()['customer']['identification'] === '900123456'
            && $request->data()['numbering_range_id'] === 28
        );
    }

    public function test_factus_rejection_is_reported_and_saved_without_marking_the_note_sent(): void
    {
        $company = $this->authenticateWithCompany();
        $note = $this->createDraft('customer_credit_note', $this->createItem());
        $note->referenceSale->update(['factus_number' => 'SETT-90002']);
        FactusCredential::create([
            'company_id' => $company->id,
            'environment' => 'sandbox',
            'client_id' => 'client-123',
            'client_secret' => 'secret-123',
            'username' => 'empresa@example.com',
            'password' => 'super-secret',
            'active' => true,
        ]);
        Cache::flush();
        Http::preventStrayRequests();
        Http::fake([
            'api-sandbox.factus.com.co/oauth/token' => Http::response(['access_token' => 'token-123'], 200),
            'api-sandbox.factus.com.co/v2/credit-notes/validate' => Http::response(['message' => 'Documento inválido'], 422),
        ]);

        $this->post(route('commercial-documents.dian', $note))
            ->assertRedirect()
            ->assertSessionHas('error', 'La nota no se envió: Factus rechazó la nota crédito: Documento inválido');

        $note->refresh();
        $this->assertSame('error', $note->dian_status);
        $this->assertNull($note->dian_number);
        $this->assertSame('Documento inválido', $note->dian_response['message']);
    }

    public function test_supplier_debit_note_cannot_be_sent_to_dian(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createDraft('supplier_debit_note', $this->createItem());

        $this->get(route('commercial-documents.supplier-debit-notes'))
            ->assertOk()
            ->assertDontSee('Enviar a DIAN');
        $this->post(route('commercial-documents.dian', $note))->assertStatus(422);
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
            'payment_method_id' => null,
            'withholding_concept' => $type === 'customer_credit_note' ? 'none' : null,
            'correction_concept_code' => $type === 'customer_credit_note' ? '1' : null,
            'recipient_email' => 'buyer@example.com',
            'document_date' => '2026-09-26',
            'due_date' => '2026-10-26',
            'notes' => 'Documento de prueba',
            'items' => [[
                'item_id' => $item->id,
                'quantity' => $type === 'customer_credit_note' ? 1 : 2,
                'unit_price' => $type === 'purchase_order' ? 50 : 100,
                'iva_percentage' => 19,
                'discount_percentage' => 0,
                'utility_percentage' => 20,
            ]],
        ];
    }
}
