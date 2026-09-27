<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\CommercialDocument;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingVoucherControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_the_accounting_form(): void
    {
        $this->get(route('accounting.vouchers.index'))->assertRedirect(route('login'));
    }

    public function test_note_opens_a_prefilled_accounting_voucher(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createNote('customer_credit_note');

        $this->get(route('accounting.vouchers.index', ['commercial_document_id' => $note->id]))
            ->assertOk()
            ->assertViewHas('commercialDocument', fn (CommercialDocument $document): bool => $document->is($note))
            ->assertViewHas('voucherPrefill', fn (array $prefill): bool => $prefill['third_party'] === $note->third_party_name && $prefill['amount'] === '119.00');
    }

    public function test_balanced_voucher_for_the_exact_note_total_links_and_accounts_once(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createNote('customer_credit_note');
        [$debitAccount, $creditAccount] = $this->createAccounts();

        $response = $this->post(route('accounting.vouchers.store'), $this->voucherPayload($note, $debitAccount, $creditAccount));

        $voucher = AccountingVoucher::query()->where('commercial_document_id', $note->id)->firstOrFail();
        $response->assertRedirect(route('accounting.vouchers.accounting', $voucher));
        $this->assertSame('accounted', $note->fresh()->status);
        $this->assertNotNull($note->fresh()->accounted_at);
        $this->assertSame(119.0, (float) $voucher->total_debit);
        $this->assertSame(119.0, (float) $voucher->total_credit);
        $this->assertDatabaseCount('accounting_voucher_lines', 2);
        $this->get(route('commercial-documents.customer-credit-notes'))
            ->assertSeeText('Ver contabilización')
            ->assertSee(route('accounting.vouchers.accounting', $voucher), false);
    }

    public function test_balanced_voucher_with_a_different_total_does_not_account_the_note(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createNote('customer_credit_note');
        [$debitAccount, $creditAccount] = $this->createAccounts();
        $payload = $this->voucherPayload($note, $debitAccount, $creditAccount);
        $payload['lines'][0]['debit'] = 100;
        $payload['lines'][1]['credit'] = 100;

        $this->from(route('accounting.vouchers.index', ['commercial_document_id' => $note->id]))
            ->post(route('accounting.vouchers.store'), $payload)
            ->assertRedirect(route('accounting.vouchers.index', ['commercial_document_id' => $note->id]))
            ->assertSessionHasErrors('lines');

        $this->assertSame('draft', $note->fresh()->status);
        $this->assertDatabaseCount('accounting_vouchers', 0);
    }

    public function test_a_note_cannot_be_accounted_twice(): void
    {
        $this->authenticateWithCompany();
        $note = $this->createNote('supplier_debit_note');
        [$debitAccount, $creditAccount] = $this->createAccounts();
        $payload = $this->voucherPayload($note, $debitAccount, $creditAccount, 'nota_debito_proveedor');

        $this->post(route('accounting.vouchers.store'), $payload)->assertSessionHasNoErrors();
        $voucher = AccountingVoucher::query()->where('commercial_document_id', $note->id)->firstOrFail();
        $this->get(route('commercial-documents.supplier-debit-notes'))
            ->assertSeeText('Ver contabilización')
            ->assertSee(route('accounting.vouchers.accounting', $voucher), false);

        $payload['consecutive'] = 'CONT-ND-MANUAL-002';
        $this->post(route('accounting.vouchers.store'), $payload)->assertStatus(409);

        $this->assertDatabaseCount('accounting_vouchers', 1);
        $this->assertDatabaseCount('accounting_voucher_lines', 2);
    }

    public function test_another_companys_note_cannot_be_opened_or_accounted(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $note = $this->createNote('customer_credit_note', $otherCompany);

        $this->get(route('accounting.vouchers.index', ['commercial_document_id' => $note->id]))->assertNotFound();
        $this->post(route('accounting.vouchers.store'), ['commercial_document_id' => $note->id])->assertNotFound();
        $this->assertDatabaseCount('accounting_vouchers', 0);
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createNote(string $type, ?Company $company = null): CommercialDocument
    {
        $company ??= Company::query()->whereKey(session('company_id'))->firstOrFail();

        return CommercialDocument::create([
            'company_id' => $company->id,
            'document_type' => $type,
            'consecutive' => $type === 'customer_credit_note' ? 'NC-MANUAL-001' : 'ND-MANUAL-001',
            'status' => 'draft',
            'third_party_name' => 'Tercero de prueba',
            'document_date' => '2026-09-26',
            'subtotal' => 100,
            'iva_total' => 19,
            'total' => 119,
        ]);
    }

    private function createAccounts(): array
    {
        return [
            ChartOfAccount::create([
                'code' => '9911',
                'name' => 'Cuenta débito de prueba',
                'class' => 1,
                'nature' => 'debit',
                'allows_posting' => true,
                'active' => true,
            ]),
            ChartOfAccount::create([
                'code' => '9912',
                'name' => 'Cuenta crédito de prueba',
                'class' => 4,
                'nature' => 'credit',
                'allows_posting' => true,
                'active' => true,
            ]),
        ];
    }

    private function voucherPayload(CommercialDocument $note, ChartOfAccount $debitAccount, ChartOfAccount $creditAccount, string $voucherType = 'nota_credito_cliente'): array
    {
        return [
            'commercial_document_id' => $note->id,
            'voucher_type' => $voucherType,
            'voucher_date' => '2026-09-26',
            'consecutive' => 'CONT-'.$note->consecutive,
            'third_party' => $note->third_party_name,
            'description' => 'Contabilización manual '.$note->consecutive,
            'lines' => [
                ['chart_of_account_id' => $debitAccount->id, 'detail' => 'Débito manual', 'debit' => 119, 'credit' => 0],
                ['chart_of_account_id' => $creditAccount->id, 'detail' => 'Crédito manual', 'debit' => 0, 'credit' => 119],
            ],
        ];
    }
}
