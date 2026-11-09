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
}
