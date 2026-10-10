<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FactusCredential;
use App\Models\Item;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_is_accounted_automatically_with_balanced_debits_and_credits(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->createAccount('1305', 'Clientes', 1);
        $this->createAccount('4135', 'Comercio al por mayor y al por menor', 4, 'credit');
        $this->createAccount('2408', 'Impuesto sobre las ventas por pagar', 2, 'credit');

        $this->post(route('sales.store'), $this->salePayload($item, 100_000, iva: 19))
            ->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();
        $this->assertNotNull($sale->accounting_voucher_id);

        $voucher = AccountingVoucher::query()->with('lines')->findOrFail($sale->accounting_voucher_id);
        $this->assertSame((float) $voucher->total_debit, (float) $voucher->total_credit);
        $this->assertSame(119_000.0, (float) $voucher->total_debit);
    }

    public function test_sale_splits_income_lines_by_each_items_own_puc_account(): void
    {
        $this->authenticateWithCompany();
        $this->createAccount('1305', 'Clientes', 1);
        $this->createAccount('4135', 'Comercio al por mayor y al por menor', 4, 'credit');
        $serviceIncomeAccount = $this->createAccount('4155-TEST', 'Servicios de consultoría', 4, 'credit');

        $productWithoutAccount = $this->createProduct();
        $serviceWithAccount = Item::create([
            'type' => 'producto',
            'name' => 'Servicio con cuenta propia',
            'code' => 'SKU-VTA-002',
            'sale_price' => 200,
            'purchase_price' => 100,
            'stock' => 10,
            'min_stock' => 1,
            'income_account_id' => $serviceIncomeAccount->id,
        ]);

        $payload = [
            'invoice_number' => 'VTA-TEST-002',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 300_000,
            'iva_total' => 0,
            'discount_total' => 0,
            'withholding_concept' => 'none',
            'retention_base' => 0,
            'retention_total' => 0,
            'total' => 300_000,
            'items' => [
                ['item_id' => $productWithoutAccount->id, 'quantity' => 1, 'unit_price' => 100_000, 'iva_percentage' => 0, 'discount_percentage' => 0],
                ['item_id' => $serviceWithAccount->id, 'quantity' => 1, 'unit_price' => 200_000, 'iva_percentage' => 0, 'discount_percentage' => 0],
            ],
        ];

        $this->post(route('sales.store'), $payload)->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-002')->firstOrFail();
        $voucher = AccountingVoucher::query()->with('lines')->findOrFail($sale->accounting_voucher_id);

        $this->assertSame((float) $voucher->total_debit, (float) $voucher->total_credit);
        $this->assertTrue($voucher->lines->contains(fn ($line) => $line->chart_of_account_id === $serviceIncomeAccount->id && (float) $line->credit === 200_000.0));
    }

    public function test_sale_explains_which_account_is_missing_when_it_cannot_be_accounted(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();

        $response = $this->post(route('sales.store'), $this->salePayload($item, 100_000))
            ->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();
        $this->assertNull($sale->accounting_voucher_id);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'Falta la cuenta PUC 1305'));
    }

    public function test_sale_is_sent_to_factus_when_credentials_are_configured(): void
    {
        $company = $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->createFactusCredential($company);
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 600]),
            '*/v2/bills/validate' => Http::response(['data' => ['bill' => ['number' => 'FV-1', 'cufe' => 'cufe-abc']]]),
        ]);

        $this->post(route('sales.store'), $this->salePayload($item, 100_000))
            ->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();
        $this->assertSame('enviada', $sale->factus_status);
        $this->assertSame('cufe-abc', $sale->factus_cufe);
        Http::assertSentCount(2);
    }

    public function test_sale_records_the_error_when_factus_rejects_the_invoice(): void
    {
        $company = $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->createFactusCredential($company);
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 600]),
            '*/v2/bills/validate' => Http::response(['message' => 'Datos inválidos'], 422),
        ]);

        $response = $this->post(route('sales.store'), $this->salePayload($item, 100_000))
            ->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();
        $this->assertSame('error', $sale->factus_status);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'Factus rechazó la factura'));
    }

    public function test_sale_skips_factus_when_no_credentials_are_configured(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        Http::fake();

        $response = $this->post(route('sales.store'), $this->salePayload($item, 100_000))
            ->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();
        $this->assertNull($sale->factus_status);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'No se envió a la DIAN'));
        Http::assertNothingSent();
    }

    public function test_sale_marked_with_skip_dian_never_calls_factus_even_with_credentials(): void
    {
        $company = $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->createFactusCredential($company);
        Http::fake();

        $payload = $this->salePayload($item, 100_000);
        $payload['skip_dian'] = 1;

        $response = $this->post(route('sales.store'), $payload)->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();
        $this->assertTrue((bool) $sale->skip_dian);
        $this->assertNull($sale->factus_status);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'Marcada como factura interna'));
        Http::assertNothingSent();
    }

    public function test_edit_and_update_change_the_sale_header_and_line_items(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->post(route('sales.store'), $this->salePayload($item, 100_000))
            ->assertRedirect(route('sales.index'));
        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();
        $this->assertSame(9, $item->fresh()->stock);

        $this->get(route('sales.edit', $sale))->assertOk()->assertSee('VTA-TEST-001');

        $updatePayload = $this->salePayload($item, 50_000);
        $updatePayload['invoice_number'] = 'VTA-TEST-001-B';
        $updatePayload['customer_name'] = 'Cliente actualizado';
        $updatePayload['sale_date'] = now()->toDateString();
        $updatePayload['items'][0]['quantity'] = 3;

        $this->put(route('sales.update', $sale), $updatePayload)
            ->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $this->assertSame('VTA-TEST-001-B', $sale->invoice_number);
        $this->assertSame('Cliente actualizado', $sale->customer_name);
        $this->assertSame(1, $sale->details()->count());
        $this->assertSame(3, $sale->details()->first()->quantity);

        // Stock inicial 10: -1 por la venta original, +1 al revertir, -3 por la nueva cantidad = 7.
        $this->assertSame(7, $item->fresh()->stock);
    }

    public function test_statement_lists_all_sales_from_the_same_customer(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->post(route('sales.store'), $this->salePayload($item, 100_000))
            ->assertRedirect(route('sales.index'));
        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();

        $this->get(route('sales.statement', $sale))
            ->assertOk()
            ->assertSee($sale->customer_name)
            ->assertSee($sale->invoice_number);
    }

    public function test_email_sends_the_sale_invoice(): void
    {
        Mail::fake();
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->post(route('sales.store'), $this->salePayload($item, 100_000))
            ->assertRedirect(route('sales.index'));
        $sale = Sale::query()->where('invoice_number', 'VTA-TEST-001')->firstOrFail();

        $this->post(route('sales.email', $sale), ['email' => 'cliente@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Factura enviada por correo.');
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
            'name' => 'Producto de prueba',
            'code' => 'SKU-VTA-001',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 10,
            'min_stock' => 1,
        ]);
    }

    private function salePayload(Item $item, int $unitPrice, int $iva = 0): array
    {
        return [
            'invoice_number' => 'VTA-TEST-001',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => $unitPrice,
            'iva_total' => round($unitPrice * $iva / 100, 2),
            'discount_total' => 0,
            'withholding_concept' => 'none',
            'retention_base' => 0,
            'retention_total' => 0,
            'total' => round($unitPrice + $unitPrice * $iva / 100, 2),
            'items' => [[
                'item_id' => $item->id,
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'iva_percentage' => $iva,
                'discount_percentage' => 0,
            ]],
        ];
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
