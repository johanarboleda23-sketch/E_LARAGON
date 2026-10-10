<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_average_cost_is_weighted_by_quantity_across_multiple_purchases(): void
    {
        $this->authenticateWithCompany();
        $item = Item::create([
            'type' => 'producto',
            'name' => 'Producto de prueba',
            'code' => 'SKU-COST-001',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        // Compra 1: 10 unidades a $100 => costo acumulado 1000
        $this->post(route('purchases.store'), $this->purchasePayload($item, 'DAV-COST-001', 10, 100))
            ->assertRedirect(route('purchases.index'));

        $this->assertSame(100.0, (float) $item->fresh()->average_cost);

        // Compra 2: 30 unidades a $200 => (1000 + 6000) / 40 = 175
        $this->post(route('purchases.store'), $this->purchasePayload($item, 'DAV-COST-002', 30, 200))
            ->assertRedirect(route('purchases.index'));

        $this->assertSame(175.0, (float) $item->fresh()->average_cost);
        $this->assertSame(40, $item->fresh()->stock);
    }

    public function test_average_cost_is_recalculated_when_a_purchase_is_deleted(): void
    {
        $this->authenticateWithCompany();
        $item = Item::create([
            'type' => 'producto',
            'name' => 'Producto de prueba',
            'code' => 'SKU-COST-002',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 0,
            'min_stock' => 0,
        ]);

        $this->post(route('purchases.store'), $this->purchasePayload($item, 'DAV-COST-003', 10, 100))
            ->assertRedirect(route('purchases.index'));
        $this->post(route('purchases.store'), $this->purchasePayload($item, 'DAV-COST-004', 30, 200))
            ->assertRedirect(route('purchases.index'));
        $this->assertSame(175.0, (float) $item->fresh()->average_cost);

        $purchaseToDelete = Purchase::where('invoice_number', 'DAV-COST-004')->firstOrFail();
        $this->delete(route('purchases.destroy', $purchaseToDelete))->assertRedirect();

        $this->assertSame(100.0, (float) $item->fresh()->average_cost);
    }

    public function test_it_suggests_a_sale_price_from_the_desired_margin_and_can_apply_it(): void
    {
        $this->authenticateWithCompany();
        $item = Item::create([
            'type' => 'producto',
            'name' => 'Producto de prueba',
            'code' => 'SKU-COST-005',
            'sale_price' => 50,
            'purchase_price' => 50,
            'stock' => 0,
            'min_stock' => 0,
            'average_cost' => 100,
        ]);

        $this->get(route('costs.index'))->assertOk()->assertSee('Producto de prueba');

        $this->put(route('costs.update', $item), [
            'desired_margin_percentage' => 30,
            'apply_to_sale_price' => 1,
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(30.0, (float) $item->desired_margin_percentage);
        $this->assertSame(130.0, (float) $item->sale_price);
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function purchasePayload(Item $item, string $invoiceNumber, int $quantity, float $costPrice): array
    {
        return [
            'invoice_number' => $invoiceNumber,
            'provider' => 'Proveedor de prueba',
            'provider_regimen' => 'gran_contribuyente',
            'withholding_concept' => 'purchase_no_declarante',
            'items' => [[
                'purchase_line_type' => 'producto',
                'item_id' => $item->id,
                'quantity' => $quantity,
                'cost_price' => $costPrice,
                'iva_percentage' => 0,
                'utility_percentage' => 0,
            ]],
        ];
    }
}
