<?php

namespace Tests\Feature;

use App\Mail\RetentionCertificateMail;
use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\RetentionCertificate;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RetentionCertificateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_customer_and_supplier_documents_from_the_correct_puc_direction(): void
    {
        $company = $this->authenticateWithCompany();
        $customer = ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente Certificado', 'email' => 'cliente@example.com', 'is_customer' => true, 'active' => true]);
        $supplier = ThirdParty::create(['company_id' => $company->id, 'name' => 'Proveedor Certificado', 'email' => 'proveedor@example.com', 'is_supplier' => true, 'active' => true]);
        $this->createVoucherLine($company, '236501', 'Retención practicada', $customer->name, debit: 0, credit: 130000);
        $this->createVoucherLine($company, '236501', 'Reverso', $customer->name, debit: 5000, credit: 0);
        $this->createVoucherLine($company, '135501', 'Retención sufrida', $supplier->name, debit: 87500, credit: 0);

        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '236501'))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('retention-certificates.generate'), $this->generationPayload('supplier', '135501'))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('retention_certificates', ['third_party_id' => $customer->id, 'kind' => 'customer', 'total_amount' => 125000]);
        $this->assertDatabaseHas('retention_certificates', ['third_party_id' => $supplier->id, 'kind' => 'supplier', 'total_amount' => 87500]);
    }

    public function test_it_does_not_generate_a_certificate_for_another_company(): void
    {
        $company = $this->authenticateWithCompany();
        $otherCompany = Company::create(['name' => 'Otra empresa', 'active' => true]);
        ThirdParty::create(['company_id' => $otherCompany->id, 'name' => 'Tercero ajeno', 'email' => 'ajeno@example.com', 'is_customer' => true, 'active' => true]);
        $this->createVoucherLine($otherCompany, '2365', 'Retención ajena', 'Tercero ajeno', debit: 0, credit: 50000);

        $this->createVoucherLine($company, '2365', 'Propia', 'Sin tercero', debit: 0, credit: 0);
        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '2365'))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('retention_certificates', 0);
    }

    public function test_it_can_edit_and_send_a_certificate(): void
    {
        Mail::fake();
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente Envío', 'email' => 'envio@example.com', 'is_customer' => true, 'active' => true]);
        $this->createVoucherLine($company, '2365', 'Retención', $thirdParty->name, debit: 0, credit: 30000);
        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '2365'))->assertSessionHasNoErrors();
        $certificate = RetentionCertificate::firstOrFail();

        $this->patch(route('retention-certificates.update', $certificate), ['recipient_email' => 'nuevo@example.com', 'subject' => 'Asunto editado', 'body' => 'Mensaje editado'])->assertRedirect();
        $this->post(route('retention-certificates.send', $certificate->fresh()))->assertRedirect();

        Mail::assertSent(RetentionCertificateMail::class, fn (RetentionCertificateMail $mail): bool => $mail->certificate->subject === 'Asunto editado');
        $this->assertSame('sent', $certificate->fresh()->status);
    }

    public function test_it_can_send_all_certificates_with_registered_emails(): void
    {
        Mail::fake();
        $company = $this->authenticateWithCompany();
        $first = ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente Uno', 'email' => 'uno@example.com', 'is_customer' => true, 'active' => true]);
        $second = ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente Dos', 'email' => 'dos@example.com', 'is_customer' => true, 'active' => true]);
        ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente Sin Correo', 'is_customer' => true, 'active' => true]);
        $this->createVoucherLine($company, '2365', 'Retención uno', $first->name, debit: 0, credit: 10000);
        $this->createVoucherLine($company, '2365', 'Retención dos', $second->name, debit: 0, credit: 20000);
        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '2365'))->assertSessionHasNoErrors();

        $this->post(route('retention-certificates.send-all'), ['taxable_year' => 2026, 'kind' => 'customer'])->assertRedirect();

        Mail::assertSentCount(2);
        $this->assertSame(2, RetentionCertificate::query()->where('status', 'sent')->count());

        $this->post(route('retention-certificates.send-all'), ['taxable_year' => 2026, 'kind' => 'customer'])->assertRedirect();
        Mail::assertSentCount(2);
    }

    public function test_generation_does_not_overwrite_an_edited_document(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente', 'email' => 'cliente@example.com', 'is_customer' => true]);
        $this->createVoucherLine($company, '2365', 'Retención', $thirdParty->name, debit: 0, credit: 30000);
        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '2365'))->assertSessionHasNoErrors();
        $certificate = RetentionCertificate::firstOrFail();
        $certificate->update(['subject' => 'Asunto revisado']);

        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '2365'))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('Asunto revisado', $certificate->fresh()->subject);
        $this->assertDatabaseCount('retention_certificates', 1);
    }

    public function test_ambiguous_third_party_is_omitted_instead_of_sending_to_the_wrong_person(): void
    {
        $company = $this->authenticateWithCompany();
        ThirdParty::create(['company_id' => $company->id, 'name' => 'Nombre repetido', 'email' => 'uno@example.com', 'is_customer' => true]);
        ThirdParty::create(['company_id' => $company->id, 'name' => 'Nombre repetido', 'email' => 'dos@example.com', 'is_customer' => true]);
        $this->createVoucherLine($company, '2365', 'Retención', 'Nombre repetido', debit: 0, credit: 10000);

        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '2365'))
            ->assertRedirect()->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'no se pudieron identificar de forma única'));

        $this->assertDatabaseCount('retention_certificates', 0);
    }

    public function test_generation_requires_accounts_from_the_active_company(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $account = ChartOfAccount::create([
            'company_id' => $otherCompany->id, 'code' => '236501', 'name' => 'Retención ajena',
            'class' => 2, 'nature' => 'credit', 'allows_posting' => true,
        ]);

        $this->post(route('retention-certificates.generate'), ['taxable_year' => 2026, 'kind' => 'customer'])
            ->assertSessionHasErrors('account_ids');
        $this->post(route('retention-certificates.generate'), ['taxable_year' => 2026, 'kind' => 'customer', 'account_ids' => [$account->id]])
            ->assertSessionHasErrors('account_ids.0');
        $this->assertDatabaseCount('retention_certificates', 0);
    }

    public function test_user_with_read_only_role_cannot_send_or_generate(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'consulta']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->post(route('retention-certificates.generate'), ['taxable_year' => 2026, 'kind' => 'customer'])->assertForbidden();
        $this->post(route('retention-certificates.send-all'), ['taxable_year' => 2026, 'kind' => 'customer'])->assertForbidden();
    }

    public function test_document_can_be_viewed_and_deleted_only_by_its_company(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create(['company_id' => $company->id, 'name' => 'Cliente visible', 'document' => '900123456', 'is_customer' => true]);
        $this->createVoucherLine($company, '2365', 'Retención', $thirdParty->name, debit: 0, credit: 50000);
        $this->post(route('retention-certificates.generate'), $this->generationPayload('customer', '2365'))->assertSessionHasNoErrors();
        $certificate = RetentionCertificate::firstOrFail();

        $this->get(route('retention-certificates.download', $certificate))->assertOk()->assertSeeText('Cliente visible')->assertSeeText('900123456')->assertSeeText('50.000,00');

        $otherCompany = Company::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->companies()->attach($otherCompany->id, ['role' => 'admin']);
        $this->actingAs($otherUser)->withSession(['company_id' => $otherCompany->id]);
        $this->get(route('retention-certificates.download', $certificate))->assertNotFound();
        $this->delete(route('retention-certificates.destroy', $certificate))->assertNotFound();
        $this->assertDatabaseHas('retention_certificates', ['id' => $certificate->id]);
    }

    public function test_partial_migration_restores_indexes_without_deleting_existing_documents(): void
    {
        $company = $this->authenticateWithCompany();
        $certificate = RetentionCertificate::create([
            'company_id' => $company->id, 'taxable_year' => 2026, 'kind' => 'customer',
            'third_party_name' => 'Cliente', 'subject' => 'Certificado', 'body' => 'Contenido',
        ]);
        Schema::table('retention_certificates', function (Blueprint $table): void {
            $table->dropUnique('ret_cert_company_year_party_unique');
            $table->dropIndex('ret_cert_company_year_kind_idx');
        });

        $migration = require database_path('migrations/2026_11_24_053511_create_retention_certificates_table.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('retention_certificates', ['id' => $certificate->id, 'body' => 'Contenido']);
        $this->assertTrue(Schema::hasIndex('retention_certificates', ['company_id', 'taxable_year', 'kind', 'third_party_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('retention_certificates', 'ret_cert_company_year_kind_idx'));
    }

    private function authenticateWithCompany(): Company
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Empresa de prueba', 'active' => true]);
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function generationPayload(string $kind, string $accountCode): array
    {
        return ['taxable_year' => 2026, 'kind' => $kind, 'account_ids' => [ChartOfAccount::query()->where('code', $accountCode)->value('id')]];
    }

    private function createVoucherLine(Company $company, string $accountCode, string $detail, string $thirdParty, float $debit, float $credit): void
    {
        $account = ChartOfAccount::firstOrCreate(
            ['company_id' => $company->id, 'code' => $accountCode],
            ['name' => $detail, 'class' => (int) $accountCode[0], 'nature' => 'credit', 'allows_posting' => true, 'active' => true]
        );
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'voucher_type' => 'egreso',
            'consecutive' => 'CE-'.$accountCode.'-'.$company->id.'-'.uniqid(),
            'voucher_date' => '2026-09-14',
            'third_party' => $thirdParty,
            'total_debit' => max($debit, $credit),
            'total_credit' => max($debit, $credit),
        ]);
        $voucher->lines()->create(['company_id' => $company->id, 'chart_of_account_id' => $account->id, 'detail' => $detail, 'debit' => $debit, 'credit' => $credit]);
    }
}
