<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_search_indexes_requested_accounting_and_payroll_keywords(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('nomina nominas seguridad social pila aportes planilla', false)
            ->assertSee('egreso egresos recibo recibos de caja comprobante comprobantes contable contables', false)
            ->assertSee('Terceros')
            ->assertSee('Buscar módulo, acción o tercero...');
    }

    public function test_dashboard_groups_commercial_documents_and_keeps_each_link(): void
    {
        $response = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Notas comerciales')
            ->assertSeeText('Documentos comerciales');

        foreach ([
            'commercial-documents.customer-credit-notes',
            'commercial-documents.customer-debit-notes',
            'commercial-documents.supplier-credit-notes',
            'commercial-documents.supplier-debit-notes',
            'commercial-documents.quotations',
            'commercial-documents.sales-orders',
            'commercial-documents.remissions',
            'commercial-documents.purchase-orders',
        ] as $routeName) {
            $response->assertSee(route($routeName));
        }
    }

    public function test_authenticated_dashboard_configures_live_third_party_search(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Buscar módulo, acción o tercero...')
            ->assertSee(route('third-parties.search'));
    }

    public function test_authenticated_users_can_access_main_modules_from_shared_and_standalone_pages(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'consulta']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        foreach (['dashboard', 'sales.index', 'purchases.index', 'bank-reconciliation.index', 'bulk-operations.index', 'cash-register.index', 'reports.index'] as $pageRoute) {
            $this->get(route($pageRoute))
                ->assertOk()
                ->assertSee('aria-label="Navegación principal"', false)
                ->assertSee(route('sales.index'))
                ->assertSee(route('purchases.index'))
                ->assertSee(route('cash-register.index'))
                ->assertSee(route('reports.index'));
        }
    }

    public function test_guest_navigation_does_not_expose_module_menu(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('aria-label="Accesos a módulos"', false);
    }

    public function test_standalone_navigation_shows_administration_only_to_administrators(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'consulta']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('sales.index'))->assertOk()->assertDontSee(route('admin.index'));

        $user->companies()->updateExistingPivot($company->id, ['role' => 'admin']);
        $this->get(route('sales.index'))->assertOk()->assertSee(route('admin.index'));
    }
}
