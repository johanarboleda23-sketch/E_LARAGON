<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FactusCredential;
use App\Models\Item;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_sale_decrements_stock_and_is_accounted_automatically(): void
    {
        $this->authenticateWithCompany();
        $product = $this->createProduct();
        $this->createAccount('4135', 'Comercio al por mayor y al por menor', 4, 'credit');
        $this->createAccount('2408', 'Impuesto a las ventas por pagar', 2, 'credit');
        $this->createAccount('1305', 'Clientes', 1, 'credit');

        $this->post(route('pos.store'), [
            'customer_name' => 'Cliente mostrador',
            'items' => [
                ['item_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertStringStartsWith('POS-', $sale->invoice_number);
        $this->assertSame('Cliente mostrador', $sale->customer_name);
        $this->assertSame(200.0, (float) $sale->subtotal);

        $product->refresh();
        $this->assertSame(8, $product->stock);
    }

    public function test_pos_sale_is_sent_to_factus_when_credentials_are_configured(): void
    {
        $company = $this->authenticateWithCompany();
        $product = $this->createProduct();
        $this->createFactusCredential($company);
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 600]),
            '*/v2/bills/validate' => Http::response(['data' => ['bill' => ['number' => 'POS-1', 'cufe' => 'cufe-pos']]]),
        ]);

        $this->post(route('pos.store'), [
            'items' => [
                ['item_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('enviada', $sale->factus_status);
        $this->assertSame('cufe-pos', $sale->factus_cufe);
        $this->assertSame('Consumidor final', $sale->customer_name);
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createProduct(): Item
    {
        return Item::create([
            'type' => 'producto',
            'name' => 'Producto POS',
            'code' => 'SKU-POS-001',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 10,
            'min_stock' => 1,
        ]);
    }

    private function createAccount(string $code, string $name, int $class, string $nature = 'debit'): ChartOfAccount
    {
        return ChartOfAccount::create([
            'code' => $code,
            'name' => $name,
            'class' => $class,
            'nature' => $nature,
            'allows_posting' => true,
            'active' => true,
        ]);
    }

    private function createFactusCredential(Company $company): FactusCredential
    {
        return FactusCredential::create([
            'company_id' => $company->id,
            'environment' => 'sandbox',
            'client_id' => 'client-123',
            'client_secret' => 'secret-123',
            'username' => 'empresa@example.com',
            'password' => 'super-secret',
            'invoice_numbering_range_id' => 4,
            'active' => true,
        ]);
    }
}
