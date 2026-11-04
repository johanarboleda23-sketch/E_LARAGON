<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\OpeningBalanceInvoice;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OpeningBalanceInvoiceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_opening_balance_cartera(): void
    {
        $this->get(route('accounting.opening-balances.index'))
            ->assertRedirect(route('login'));
    }

    public function test_opening_balance_template_can_be_downloaded(): void
    {
        $this->authenticateWithCompany();

        $this->get(route('accounting.opening-balances.template'))
            ->assertOk()
            ->assertSee('Tipo cartera', false)
            ->assertSee('CUFE', false);
    }

    public function test_opening_balance_workspace_and_cartera_render_the_import_and_payment_controls(): void
    {
        $company = $this->authenticateWithCompany();
        $invoice = OpeningBalanceInvoice::create([
            'company_id' => $company->id,
            'invoice_type' => 'cliente',
            'third_party_name' => 'Cliente visible',
            'third_party_document' => '900123456',
            'invoice_number' => 'FV-VISIBLE',
            'invoice_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'cufe' => str_repeat('B', 96),
            'opening_balance' => 100000,
        ]);
        $invoice->payments()->create([
            'company_id' => $company->id,
            'amount' => 25000,
            'payment_date' => '2026-01-20',
        ]);

        $this->get(route('accounting.vouchers.index.typed', 'saldos_iniciales'))
            ->assertOk()
            ->assertSee('Importar Excel')
            ->assertSee('No se vuelven a crear compras o ventas ni se mueve inventario.');

        $this->get(route('accounting.opening-balances.index', ['search' => 'FV-VISIBLE']))
            ->assertOk()
            ->assertSee('FV-VISIBLE')
            ->assertSee('Cliente visible')
            ->assertSee(str_repeat('B', 96))
            ->assertSee('Registrar abono')
            ->assertSee('75.000,00');
    }

    public function test_import_creates_opening_balance_voucher_and_cartera_without_purchase_or_sale(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'type' => 'juridica',
            'name' => 'Cliente de prueba',
            'document' => '900123456',
            'is_customer' => true,
            'active' => true,
        ]);
        $this->createAccount($company, '1305', 'Clientes', 1);
        $this->createAccount($company, '3105', 'Capital', 3, 'credit');

        $csv = implode("\r\n", [
            '"Tipo cartera";"Documento tercero";"Factura";"Fecha factura";"Fecha vencimiento";"CUFE";"Cuenta PUC";"Débito";"Crédito";"Detalle"',
            '"cliente";"900123456";"FV-001";"2026-01-15";"2026-02-15";"";"1305";"250000";"0";"Saldo inicial cliente"',
            '"";"";"";"";"";"";"3105";"0";"250000";"Contrapartida de apertura"',
        ]);
        $cufe = str_repeat('A', 96);
        $xml = '<Invoice xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2">'
            .'<cbc:ID>FV-001</cbc:ID><cbc:IssueDate>2026-01-15</cbc:IssueDate><cbc:DueDate>2026-02-15</cbc:DueDate>'
            .'<cbc:UUID schemeName="CUFE-SHA384">'.$cufe.'</cbc:UUID>'
            .'<cac:AccountingCustomerParty><cac:Party><cac:PartyTaxScheme><cbc:CompanyID>900123456</cbc:CompanyID></cac:PartyTaxScheme></cac:Party></cac:AccountingCustomerParty>'
            .'</Invoice>';

        $response = $this->post(route('accounting.opening-balances.import'), [
            'voucher_date' => '2026-01-01',
            'file' => UploadedFile::fake()->createWithContent('saldos.csv', $csv),
            'xml_files' => [
                UploadedFile::fake()->createWithContent('factura.xml', $xml),
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $voucher = AccountingVoucher::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->firstOrFail();
        $response->assertRedirect(route('accounting.vouchers.accounting', $voucher));
        $this->assertSame('saldos_iniciales', $voucher->voucher_type);
        $this->assertDatabaseHas('opening_balance_invoices', [
            'company_id' => $company->id,
            'third_party_id' => $thirdParty->id,
            'invoice_number' => 'FV-001',
            'cufe' => $cufe,
            'opening_balance' => 250000,
            'accounting_voucher_id' => $voucher->id,
        ]);
        $this->assertDatabaseCount('purchases', 0);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_import_rejects_negative_amounts_without_creating_a_voucher(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'type' => 'juridica',
            'name' => 'Cliente de prueba',
            'document' => '900123456',
            'is_customer' => true,
            'active' => true,
        ]);
        $this->createAccount($company, '1305', 'Clientes', 1);
        $this->createAccount($company, '3105', 'Capital', 3, 'credit');

        $csv = implode("\r\n", [
            '"Tipo cartera";"Documento tercero";"Factura";"Fecha factura";"Fecha vencimiento";"CUFE";"Cuenta PUC";"Débito";"Crédito";"Detalle"',
            '"cliente";"900123456";"FV-NEG";"2026-01-15";"";"";"1305";"250000";"-1";"Saldo inicial cliente"',
            '"";"";"";"";"";"";"3105";"0";"249999";"Contrapartida"',
        ]);

        $this->from(route('accounting.vouchers.index.typed', 'saldos_iniciales'))
            ->post(route('accounting.opening-balances.import'), [
                'voucher_date' => '2026-01-01',
                'file' => UploadedFile::fake()->createWithContent('saldos.csv', $csv),
            ])
            ->assertRedirect(route('accounting.vouchers.index.typed', 'saldos_iniciales'))
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('accounting_vouchers', 0);
        $this->assertDatabaseCount('opening_balance_invoices', 0);
        $this->assertSame('900123456', $thirdParty->document);
    }

    public function test_payment_reduces_the_open_balance_and_cannot_exceed_it(): void
    {
        $company = $this->authenticateWithCompany();
        $invoice = OpeningBalanceInvoice::create([
            'company_id' => $company->id,
            'invoice_type' => 'cliente',
            'third_party_name' => 'Cliente de prueba',
            'third_party_document' => '900123456',
            'invoice_number' => 'FV-002',
            'invoice_date' => '2026-01-15',
            'opening_balance' => 100000,
        ]);

        $this->post(route('accounting.opening-balances.payments.store', $invoice), [
            'amount' => 25000,
            'payment_date' => '2026-01-20',
            'method' => 'Transferencia',
        ])->assertRedirect(route('accounting.opening-balances.index', ['type' => 'cliente']));

        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'payable_type' => OpeningBalanceInvoice::class,
            'payable_id' => $invoice->id,
            'amount' => 25000,
        ]);

        $this->from(route('accounting.opening-balances.index'))
            ->post(route('accounting.opening-balances.payments.store', $invoice), [
                'amount' => 80000,
                'payment_date' => '2026-01-21',
            ])
            ->assertRedirect(route('accounting.opening-balances.index'))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_invoice_from_another_company_is_not_accessible(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $invoice = OpeningBalanceInvoice::create([
            'company_id' => $otherCompany->id,
            'invoice_type' => 'proveedor',
            'third_party_name' => 'Proveedor ajeno',
            'third_party_document' => '900654321',
            'invoice_number' => 'FC-AJENA',
            'invoice_date' => '2026-01-15',
            'opening_balance' => 50000,
        ]);

        $this->post(route('accounting.opening-balances.payments.store', $invoice), [
            'amount' => 10000,
            'payment_date' => '2026-01-20',
        ])->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createAccount(Company $company, string $code, string $name, int $class, string $nature = 'debit'): ChartOfAccount
    {
        return ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'class' => $class,
            'nature' => $nature,
            'allows_posting' => true,
            'active' => true,
        ]);
    }
}
