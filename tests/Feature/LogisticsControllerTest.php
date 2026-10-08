<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('logistics.index'))->assertRedirect(route('login'));
    }

    public function test_index_loads_without_a_mapbox_token_configured(): void
    {
        config(['services.mapbox.token' => null]);
        $this->authenticateWithCompany();

        $this->get(route('logistics.index'))
            ->assertOk()
            ->assertSee('Logística de rutas');
    }

    public function test_a_route_can_be_created(): void
    {
        $this->authenticateWithCompany();

        $this->post(route('logistics.store'), [
            'code' => 'RUTA-001',
            'vehicle_plate' => 'ABC123',
            'driver_name' => 'Juan Pérez',
            'route_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('delivery_routes', ['code' => 'RUTA-001']);
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }
}
