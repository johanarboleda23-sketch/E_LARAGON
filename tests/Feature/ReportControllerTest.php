<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Purchase;
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
}
