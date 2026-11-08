<?php

namespace Tests\Feature;

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
            ->assertSee('Terceros');
    }
}
