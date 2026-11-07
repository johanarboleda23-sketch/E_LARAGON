<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FactusCredential;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PosControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_sale_decrements_stock_and_is_accounted_automatically(): void
    {
        $company = $this->authenticateWithCompany();
        $product = $this->createProduct();
        $paymentMethod = $this->createPaymentMethod($company);
        $this->createAccount('4135', 'Comercio al por mayor y al por menor', 4, 'credit');
        $this->createAccount('2408', 'Impuesto a las ventas por pagar', 2, 'credit');
        $this->createAccount('1305', 'Clientes', 1, 'credit');

        $this->post(route('pos.store'), [
            'customer_name' => 'Cliente mostrador',
            'payment_method_id' => $paymentMethod->id,
            'items' => [
                ['item_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertStringStartsWith('POS-', $sale->invoice_number);
        $this->assertSame('Cliente mostrador', $sale->customer_name);
        $this->assertSame($paymentMethod->id, $sale->payment_method_id);
        $this->assertSame(200.0, (float) $sale->subtotal);
        $this->assertSame(38.0, (float) $sale->iva_total);

        $product->refresh();
        $this->assertSame(8.0, $product->stock);
    }

    public function test_pos_sale_uses_the_selected_conversion_for_price_and_stock(): void
    {
        $company = $this->authenticateWithCompany();
        $product = $this->createProduct();
        $paymentMethod = $this->createPaymentMethod($company);
        $conversion = $product->unitConversions()->create([
            'company_id' => $company->id,
            'name' => 'caja',
            'factor' => 6,
            'sale_price' => 500,
        ]);

        $this->post(route('pos.store'), [
            'customer_name' => 'Cliente de caja',
            'payment_method_id' => $paymentMethod->id,
            'items' => [[
                'item_id' => $product->id,
                'quantity' => 0.5,
                'unit_conversion_id' => $conversion->id,
            ]],
        ])->assertSessionHasNoErrors();

        $detail = SaleDetail::query()->latest('id')->firstOrFail();
        $this->assertSame(7.0, (float) $product->fresh()->stock);
        $this->assertSame('caja', $detail->unit_name);
        $this->assertSame(6.0, (float) $detail->unit_factor);
        $this->assertSame(0.5, $detail->quantity);
        $this->assertSame(500.0, (float) $detail->unit_price);
        $this->assertSame(250.0, (float) $detail->line_total);
    }

    public function test_pos_sale_is_sent_to_factus_when_credentials_are_configured(): void
    {
        $company = $this->authenticateWithCompany();
        $product = $this->createProduct();
        $paymentMethod = $this->createPaymentMethod($company);
        $this->createFactusCredential($company);
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 600]),
            '*/v2/bills/validate' => Http::response(['data' => ['bill' => ['number' => 'POS-1', 'cufe' => 'cufe-pos']]]),
        ]);

        $this->post(route('pos.store'), [
            'payment_method_id' => $paymentMethod->id,
            'items' => [
                ['item_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame('enviada', $sale->factus_status);
        $this->assertSame('cufe-pos', $sale->factus_cufe);
        $this->assertSame('Consumidor final', $sale->customer_name);
    }

    public function test_pos_sale_can_be_saved_without_iva(): void
    {
        $company = $this->authenticateWithCompany();
        $product = $this->createProduct();
        $paymentMethod = $this->createPaymentMethod($company);
        $this->createAccount('4135', 'Ingresos por ventas', 4, 'credit');
        $this->createAccount('1305', 'Clientes', 1, 'credit');

        $this->post(route('pos.store'), [
            'payment_method_id' => $paymentMethod->id,
            'apply_iva' => '0',
            'items' => [
                ['item_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $this->assertSame(0.0, (float) $sale->iva_total);
        $this->assertSame(100.0, (float) $sale->total);
        $this->assertSame(0.0, (float) $sale->details()->firstOrFail()->iva_percentage);
    }

    public function test_pos_requires_a_payment_method(): void
    {
        $this->authenticateWithCompany();
        $product = $this->createProduct();

        $this->post(route('pos.store'), [
            'items' => [
                ['item_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('payment_method_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10.0, (float) $product->fresh()->stock);
    }

    public function test_pos_rejects_a_payment_method_from_another_company(): void
    {
        $this->authenticateWithCompany();
        $product = $this->createProduct();
        $otherCompany = Company::factory()->create();
        $foreignMethod = $this->createPaymentMethod($otherCompany);
        $foreignMethod->company_id = $otherCompany->id;
        $foreignMethod->save();

        $this->post(route('pos.store'), [
            'payment_method_id' => $foreignMethod->id,
            'items' => [
                ['item_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertSessionHasErrors('payment_method_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10.0, (float) $product->fresh()->stock);
    }

    public function test_pos_page_shows_product_code_search_and_payment_method(): void
    {
        $this->authenticateWithCompany();
        $this->createProduct();

        $this->get(route('pos.index'))
            ->assertOk()
            ->assertSeeText('SKU-POS-001')
            ->assertSee('id="pos-product-search"', false)
            ->assertSeeText('Forma de pago');
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

    private function createPaymentMethod(Company $company): PaymentMethod
    {
        return PaymentMethod::create([
            'company_id' => $company->id,
            'name' => 'Efectivo',
            'is_cash' => true,
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
