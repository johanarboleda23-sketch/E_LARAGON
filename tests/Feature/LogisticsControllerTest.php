<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
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

    public function test_driver_can_confirm_a_delivery_with_a_timestamp(): void
    {
        $company = $this->authenticateWithCompany();
        $stop = $this->createStop($company);

        $this->post(route('logistics.stops.deliver', $stop), ['status' => 'delivered'])->assertRedirect();

        $stop->refresh();
        $this->assertSame('delivered', $stop->status);
        $this->assertNotNull($stop->delivered_at);
    }

    public function test_a_confirmed_delivery_is_locked_and_cannot_be_changed_again(): void
    {
        $company = $this->authenticateWithCompany();
        $stop = $this->createStop($company);
        $this->post(route('logistics.stops.deliver', $stop), ['status' => 'delivered'])->assertRedirect();
        $stop->refresh();
        $confirmedAt = $stop->delivered_at;

        $this->post(route('logistics.stops.deliver', $stop), ['status' => 'failed'])->assertStatus(422);

        $stop->refresh();
        $this->assertSame('delivered', $stop->status);
        $this->assertTrue($confirmedAt->equalTo($stop->delivered_at));
    }

    private function createStop(Company $company): DeliveryStop
    {
        $route = DeliveryRoute::create([
            'company_id' => $company->id,
            'code' => 'RUTA-001',
            'vehicle_plate' => 'ABC123',
            'driver_name' => 'Juan Pérez',
            'route_date' => now()->toDateString(),
            'status' => 'planned',
        ]);

        return DeliveryStop::create([
            'delivery_route_id' => $route->id,
            'sequence' => 1,
            'customer_name' => 'Cliente de prueba',
            'address' => 'Calle 123',
            'difficulty_score' => 50,
            'status' => 'pending',
        ]);
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
