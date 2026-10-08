<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FactusCredential;
use App\Models\NumberingResolution;
use App\Models\SupportDocument;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SupportDocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_document_is_accounted_automatically_with_balanced_debits_and_credits(): void
    {
        $this->authenticateWithCompany();
        $supplier = $this->createSupplier();
        $this->createAccount('5195', 'Gastos diversos', 5);
        $this->createAccount('2365', 'Retención en la fuente', 2, 'credit');
        $this->createAccount('2205', 'Proveedores nacionales', 2, 'credit');

        $this->post(route('support-documents.store'), $this->payload($supplier, subtotal: 1_000_000))
            ->assertSessionHasNoErrors();

        $document = SupportDocument::query()->where('consecutive', 'DS-TEST-001')->firstOrFail();
        $this->assertNotNull($document->accounting_voucher_id);

        $voucher = AccountingVoucher::query()->with('lines')->findOrFail($document->accounting_voucher_id);
        $this->assertSame((float) $voucher->total_debit, (float) $voucher->total_credit);
        $this->assertSame(1_000_000.0, (float) $voucher->total_debit);
    }

    public function test_support_document_explains_which_account_is_missing_when_it_cannot_be_accounted(): void
    {
        $this->authenticateWithCompany();
        $supplier = $this->createSupplier();

        $response = $this->post(route('support-documents.store'), $this->payload($supplier, subtotal: 1_000_000))
            ->assertSessionHasNoErrors();

        $document = SupportDocument::query()->where('consecutive', 'DS-TEST-001')->firstOrFail();
        $this->assertNull($document->accounting_voucher_id);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'Falta la cuenta PUC 5195'));
    }

    public function test_support_document_is_sent_to_factus_when_credentials_are_configured(): void
    {
        $company = $this->authenticateWithCompany();
        $supplier = $this->createSupplier();
        $this->createFactusCredential($company);
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 600]),
            '*/v2/support-documents/validate' => Http::response(['data' => ['bill' => ['number' => 'DS-1', 'cufe' => 'cuds-abc']]]),
        ]);

        $this->post(route('support-documents.store'), $this->payload($supplier, subtotal: 1_000_000))
            ->assertSessionHasNoErrors();

        $document = SupportDocument::query()->where('consecutive', 'DS-TEST-001')->firstOrFail();
        $this->assertSame('enviada', $document->factus_status);
        $this->assertSame('cuds-abc', $document->factus_cufe);
    }

    public function test_support_document_skips_factus_when_no_credentials_are_configured(): void
    {
        $this->authenticateWithCompany();
        $supplier = $this->createSupplier();
        Http::fake();

        $response = $this->post(route('support-documents.store'), $this->payload($supplier, subtotal: 1_000_000))
            ->assertSessionHasNoErrors();

        $document = SupportDocument::query()->where('consecutive', 'DS-TEST-001')->firstOrFail();
        $this->assertNull($document->factus_status);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'No se envió a la DIAN'));
        Http::assertNothingSent();
    }

    public function test_support_document_uses_automatic_sequential_numbering_when_a_resolution_is_active(): void
    {
        $this->authenticateWithCompany();
        $supplier = $this->createSupplier();
        NumberingResolution::create([
            'document_type' => 'support_document',
            'prefix' => 'DS',
            'resolution_number' => '0001',
            'range_from' => 1,
            'range_to' => 100,
            'next_number' => 1,
            'active' => true,
        ]);

        $payload = $this->payload($supplier, subtotal: 1_000_000);
        unset($payload['consecutive']);

        $this->post(route('support-documents.store'), $payload)
            ->assertSessionHasNoErrors();

        $document = SupportDocument::query()->latest('id')->firstOrFail();
        $this->assertSame('DS-1', $document->consecutive);
    }

    public function test_edit_and_update_change_the_support_document_header(): void
    {
        $this->authenticateWithCompany();
        $supplier = $this->createSupplier();
        $this->post(route('support-documents.store'), $this->payload($supplier, subtotal: 1_000_000))
            ->assertSessionHasNoErrors();
        $document = SupportDocument::query()->where('consecutive', 'DS-TEST-001')->firstOrFail();

        $this->get(route('support-documents.edit', $document))->assertOk()->assertSee('DS-TEST-001');

        $this->put(route('support-documents.update', $document), [
            'consecutive' => 'DS-TEST-001-B',
            'document_date' => now()->toDateString(),
            'third_party_id' => $supplier->id,
            'concept' => 'Concepto actualizado',
        ])->assertRedirect(route('support-documents.show', $document));

        $this->assertSame('Concepto actualizado', $document->fresh()->concept);
    }

    public function test_statement_lists_all_documents_from_the_same_supplier(): void
    {
        $this->authenticateWithCompany();
        $supplier = $this->createSupplier();
        $this->post(route('support-documents.store'), $this->payload($supplier, subtotal: 1_000_000))
            ->assertSessionHasNoErrors();
        $document = SupportDocument::query()->where('consecutive', 'DS-TEST-001')->firstOrFail();

        $this->get(route('support-documents.statement', $document))
            ->assertOk()
            ->assertSee($supplier->name)
            ->assertSee($document->consecutive);
    }

    public function test_email_sends_the_support_document(): void
    {
        Mail::fake();
        $this->authenticateWithCompany();
        $supplier = $this->createSupplier();
        $this->post(route('support-documents.store'), $this->payload($supplier, subtotal: 1_000_000))
            ->assertSessionHasNoErrors();
        $document = SupportDocument::query()->where('consecutive', 'DS-TEST-001')->firstOrFail();

        $this->post(route('support-documents.email', $document), ['email' => 'proveedor@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Documento soporte enviado por correo.');
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createSupplier(): ThirdParty
    {
        return ThirdParty::create([
            'type' => 'natural',
            'name' => 'Proveedor de prueba',
            'document' => '900987654',
            'email' => 'supplier@example.com',
            'is_supplier' => true,
            'active' => true,
        ]);
    }

    private function payload(ThirdParty $supplier, int $subtotal): array
    {
        return [
            'consecutive' => 'DS-TEST-001',
            'document_date' => now()->toDateString(),
            'third_party_id' => $supplier->id,
            'concept' => 'Servicio de prueba',
            'subtotal' => $subtotal,
            'iva_total' => 0,
            'withholding_concept' => 'none',
        ];
    }

    private function createAccount(string $code, string $name, int $class, string $nature = 'debit'): ChartOfAccount
    {
        return ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'class' => $class,
            'nature' => $nature,
            'allows_posting' => true,
            'active' => true,
        ]);
    }

    private function createFactusCredential(Company $company): FactusCredential
    {
        return FactusCredential::create([
            'company_id' => $company->id,
            'environment' => 'sandbox',
            'client_id' => 'client-123',
            'client_secret' => 'secret-123',
            'username' => 'empresa@example.com',
            'password' => 'super-secret',
            'active' => true,
        ]);
    }
}
