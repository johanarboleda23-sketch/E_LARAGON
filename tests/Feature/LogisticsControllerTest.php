<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Sale;
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

    public function test_route_page_lists_unassigned_invoices_and_can_add_one_to_the_batch(): void
    {
        $this->authenticateWithCompany();
        $route = $this->createRoute();
        $sale = $this->createSale('FAC-LOG-001');

        $this->get(route('logistics.show', $route))
            ->assertOk()
            ->assertSee('Agregar factura al lote')
            ->assertSee('value="'.$sale->id.'"', false)
            ->assertSee('FAC-LOG-001');

        $this->post(route('logistics.invoices.store', $route), ['sale_id' => $sale->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Factura agregada al lote sin modificar el inventario.');

        $this->assertDatabaseHas('delivery_stops', [
            'delivery_route_id' => $route->id,
            'sale_id' => $sale->id,
            'customer_name' => 'Cliente de logística',
            'status' => 'pending',
        ]);
    }

    public function test_invoice_cannot_be_added_to_a_second_route(): void
    {
        $this->authenticateWithCompany();
        $route = $this->createRoute();
        $otherRoute = $this->createRoute('RUTA-002');
        $sale = $this->createSale('FAC-LOG-002');
        DeliveryStop::create([
            'delivery_route_id' => $otherRoute->id,
            'sale_id' => $sale->id,
            'sequence' => 1,
            'customer_name' => $sale->customer_name,
            'address' => 'Dirección pendiente',
            'difficulty_score' => 50,
            'status' => 'pending',
        ]);

        $this->post(route('logistics.invoices.store', $route), ['sale_id' => $sale->id])
            ->assertStatus(422)
            ->assertSeeText('La factura ya está asignada a un lote.');

        $this->assertDatabaseCount('delivery_stops', 1);
    }

    public function test_route_cannot_add_an_invoice_from_another_company(): void
    {
        $this->authenticateWithCompany();
        $route = $this->createRoute();
        $otherCompany = Company::factory()->create();
        $sale = $this->createSale('FAC-LOG-AJENA', $otherCompany->id);

        $this->post(route('logistics.invoices.store', $route), ['sale_id' => $sale->id])
            ->assertNotFound();

        $this->assertDatabaseCount('delivery_stops', 0);
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

    private function createRoute(string $code = 'RUTA-LOG'): DeliveryRoute
    {
        return DeliveryRoute::create([
            'code' => $code,
            'vehicle_plate' => 'ABC123',
            'driver_name' => 'Conductor de logística',
            'route_date' => now()->toDateString(),
            'status' => 'draft',
        ]);
    }

    private function createSale(string $invoiceNumber, ?int $companyId = null): Sale
    {
        return Sale::create([
            'company_id' => $companyId,
            'invoice_number' => $invoiceNumber,
            'customer_name' => 'Cliente de logística',
            'sale_date' => now()->toDateString(),
            'subtotal' => 100,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 100,
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
