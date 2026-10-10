<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\AccountingVoucherLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_cash_and_bank_balances_from_linked_payment_methods(): void
    {
        $company = $this->authenticateWithCompany();
        $account = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '111005',
            'name' => 'Bancolombia ahorros',
            'account_type' => 'activo',
            'class' => '1',
            'nature' => 'debito',
            'allows_posting' => true,
        ]);
        PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Bancolombia',
            'bank_name' => 'Bancolombia',
            'account_number' => '1020459855',
            'chart_of_account_id' => $account->id,
            'is_editable' => true,
        ]);
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'consecutive' => 'RC-1',
            'voucher_type' => 'recibo_caja',
            'voucher_date' => now()->toDateString(),
            'third_party' => 'Cliente',
            'total_debit' => 500000,
            'total_credit' => 500000,
        ]);
        $voucher->lines()->create([
            'company_id' => $company->id,
            'chart_of_account_id' => $account->id,
            'detail' => 'Abono',
            'debit' => 500000,
            'credit' => 0,
        ]);

        $response = $this->get(route('reports.index', $this->validFilters('all')));

        $response->assertOk();
        $response->assertSee('1020459855');
        $response->assertSee('Bancolombia');
        $response->assertSee(number_format(500000, 2));
    }

    public function test_report_lists_only_purchases_from_the_active_company(): void
    {
        $company = $this->authenticateWithCompany();
        $otherCompany = Company::create(['name' => 'Otra empresa', 'active' => true]);
        $this->createPurchase($company, 'FAC-PROPIA');
        $this->createPurchase($otherCompany, 'FAC-AJENA');

        $response = $this->get(route('reports.index', $this->validFilters('purchases')));

        $response->assertOk()->assertSee('FAC-PROPIA')->assertDontSee('FAC-AJENA');
    }

    public function test_excel_export_contains_only_purchases_from_the_active_company(): void
    {
        $company = $this->authenticateWithCompany();
        $otherCompany = Company::create(['name' => 'Otra empresa', 'active' => true]);
        $this->createPurchase($company, 'FAC-PROPIA');
        $this->createPurchase($otherCompany, 'FAC-AJENA');

        $response = $this->get(route('reports.excel', $this->validFilters('purchases')));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSeeText('FAC-PROPIA')
            ->assertDontSeeText('FAC-AJENA');
    }

    public function test_pdf_view_contains_only_purchases_from_the_active_company(): void
    {
        $company = $this->authenticateWithCompany();
        $otherCompany = Company::create(['name' => 'Otra empresa', 'active' => true]);
        $this->createPurchase($company, 'FAC-PROPIA');
        $this->createPurchase($otherCompany, 'FAC-AJENA');

        $response = $this->get(route('reports.pdf', $this->validFilters('purchases')));

        $response->assertOk()->assertSeeText('FAC-PROPIA')->assertDontSeeText('FAC-AJENA');
    }

    public function test_accounting_report_lists_only_movements_within_the_requested_account_range(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createVoucherLine($company, '2365', 'Retención en la fuente', 50000);
        $this->createVoucherLine($company, '1435', 'Inventario', 100000);

        $response = $this->get(route('reports.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => 'accounting',
            'account_from' => '2365',
            'account_to' => '2365',
        ]));

        $response->assertOk()->assertSeeText('2365 - Retención en la fuente')->assertDontSeeText('1435 - Inventario');
    }

    public function test_accounting_report_does_not_show_movements_from_another_company(): void
    {
        $company = $this->authenticateWithCompany();
        $otherCompany = Company::create(['name' => 'Otra empresa', 'active' => true]);
        $this->createVoucherLine($company, '2365', 'Retención propia', 50000);
        $this->createVoucherLine($otherCompany, '2365', 'Retención ajena', 70000);

        $response = $this->get(route('reports.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => 'accounting',
            'account_from' => '2365',
            'account_to' => '2365',
        ]));

        $response->assertOk()->assertSeeText('Retención propia')->assertDontSeeText('Retención ajena');
    }

    public function test_accounting_report_shows_every_auxiliary_movement_when_no_account_range_is_given(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createVoucherLine($company, '2365', 'Retención en la fuente', 50000);
        $this->createVoucherLine($company, '1435', 'Inventario', 100000);

        $response = $this->get(route('reports.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => 'accounting',
        ]));

        $response->assertOk()->assertSeeText('2365 - Retención en la fuente')->assertSeeText('1435 - Inventario');
    }

    public function test_accounting_report_can_be_filtered_by_third_party(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createVoucherLine($company, '2365', 'Retención proveedor A', 50000, 'Proveedor A S.A.S');
        $this->createVoucherLine($company, '2365', 'Retención proveedor B', 30000, 'Proveedor B S.A.S');

        $response = $this->get(route('reports.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => 'accounting',
            'account_from' => '2365',
            'account_to' => '2365',
            'third_party' => 'Proveedor A',
        ]));

        $response->assertOk()->assertSeeText('Proveedor A S.A.S')->assertDontSeeText('Proveedor B S.A.S');
    }

    public function test_taxes_report_lists_iva_and_withholding_from_purchases_and_sales(): void
    {
        $company = $this->authenticateWithCompany();
        Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FAC-COMPRA',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => '2026-09-14',
            'subtotal' => 1500000,
            'iva_total' => 285000,
            'retefuente' => 40000,
            'total_pagar' => 1745000,
        ]);
        Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FAC-VENTA',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => '2026-09-20',
            'subtotal' => 100,
            'iva_total' => 19,
            'total' => 119,
        ]);

        $response = $this->get(route('reports.index', [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => 'taxes',
        ]));

        $response->assertOk()
            ->assertSeeText('IVA descontable (compra)')
            ->assertSeeText('Retención en la fuente practicada')
            ->assertSeeText('IVA generado (venta)');
    }

    #[DataProvider('invalidReportFilters')]
    public function test_report_shows_a_message_for_invalid_filters(array $filters, string $expectedMessage): void
    {
        $this->authenticateWithCompany();

        $response = $this->followingRedirects()
            ->from(route('reports.index'))
            ->get(route('reports.index', $filters));

        $response->assertOk()->assertSeeText($expectedMessage);
    }

    public static function invalidReportFilters(): array
    {
        return [
            'invalid start date' => [['from' => 'not-a-date', 'to' => '2026-09-26', 'type' => 'all'], 'La fecha inicial debe tener el formato AAAA-MM-DD.'],
            'end date before start date' => [['from' => '2026-09-27', 'to' => '2026-09-26', 'type' => 'all'], 'La fecha final debe ser igual o posterior a la fecha inicial.'],
            'unknown report type' => [['from' => '2026-09-01', 'to' => '2026-09-26', 'type' => 'unknown'], 'El módulo seleccionado no es válido.'],
        ];
    }

    private function authenticateWithCompany(): Company
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Empresa de prueba', 'active' => true]);
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createPurchase(Company $company, string $invoiceNumber): Purchase
    {
        return Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => $invoiceNumber,
            'provider' => 'Proveedor de prueba',
            'purchase_date' => '2026-09-14',
            'subtotal' => 100,
            'iva_total' => 19,
            'retefuente' => 0,
            'total_pagar' => 119,
        ]);
    }

    private function validFilters(string $type): array
    {
        return [
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'type' => $type,
        ];
    }

    private function createVoucherLine(Company $company, string $accountCode, string $detail, float $amount, string $thirdParty = 'Tercero de prueba'): AccountingVoucherLine
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
            'total_debit' => $amount,
            'total_credit' => $amount,
        ]);

        return AccountingVoucherLine::create([
            'company_id' => $company->id,
            'accounting_voucher_id' => $voucher->id,
            'chart_of_account_id' => $account->id,
            'detail' => $detail,
            'debit' => $amount,
            'credit' => 0,
        ]);
    }
}
