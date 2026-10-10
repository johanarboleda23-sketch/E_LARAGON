<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PurchaseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_page_loads_with_product_and_puc_options(): void
    {
        $this->authenticateWithCompany();
        $this->createProduct();

        $this->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('Seleccione producto')
            ->assertSee('Seleccione cuenta PUC');
    }

    public function test_purchase_withholding_is_recalculated_and_subtracted_server_side(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();

        $this->post(route('purchases.store'), $this->purchasePayload($item, 2_000_000))
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-001')->firstOrFail();
        $this->assertSame(2_000_000.0, (float) $purchase->subtotal);
        $this->assertSame(70_000.0, (float) $purchase->retefuente);
        $this->assertSame(1_930_000.0, (float) $purchase->total_pagar);
        $this->assertSame(11, $item->fresh()->stock);
    }

    public function test_purchase_is_accounted_when_payment_method_has_a_puc_account(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $expenseAccount = $this->createAccount('5105-TEST', 'Gasto de prueba', 5);
        $bankAccount = $this->createAccount('1110-TEST', 'Bancos de prueba', 1);
        $paymentMethod = PaymentMethod::create(['name' => 'Banco prueba', 'is_editable' => true, 'chart_of_account_id' => $bankAccount->id]);
        $payload = $this->purchasePayload($item, 100_000);
        $payload['invoice_number'] = 'DAV-CONT-001';
        $payload['payment_method_id'] = $paymentMethod->id;
        $payload['items'][0]['purchase_line_type'] = 'gasto';
        $payload['items'][0]['chart_of_account_id'] = $expenseAccount->id;
        unset($payload['items'][0]['item_id']);

        $this->post(route('purchases.store'), $payload)->assertRedirect(route('purchases.index'));

        $voucher = AccountingVoucher::query()->where('consecutive', 'COMP-DAV-CONT-001')->with('lines')->firstOrFail();
        $this->assertSame(100_000.0, (float) $voucher->total_debit);
        $this->assertSame(100_000.0, (float) $voucher->total_credit);
        $this->assertTrue($voucher->lines->contains('chart_of_account_id', $bankAccount->id));
    }

    public function test_purchase_does_not_withhold_below_the_configured_uvt_threshold(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $payload = $this->purchasePayload($item, 400_000);
        $payload['invoice_number'] = 'DAV-RET-002';

        $this->post(route('purchases.store'), $payload)
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-002')->firstOrFail();
        $this->assertSame(0.0, (float) $purchase->retefuente);
        $this->assertSame(400_000.0, (float) $purchase->total_pagar);
    }

    public function test_purchase_withholds_from_iva_and_subtracts_it_from_the_total(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $payload = $this->purchasePayload($item, 100_000);
        $payload['invoice_number'] = 'DAV-RET-003';
        $payload['withholding_concept'] = 'vat';
        $payload['items'][0]['iva_percentage'] = 19;

        $this->post(route('purchases.store'), $payload)
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-003')->firstOrFail();
        $this->assertSame(19_000.0, (float) $purchase->iva_total);
        $this->assertSame(2_850.0, (float) $purchase->retefuente);
        $this->assertSame(116_150.0, (float) $purchase->total_pagar);
    }

    public function test_no_responsibility_supplier_cannot_skip_purchase_withholding(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $payload = $this->purchasePayload($item, 2_000_000);
        $payload['invoice_number'] = 'DAV-RET-004';
        $payload['provider_regimen'] = 'sin_responsabilidad';
        $payload['withholding_concept'] = 'none';

        $this->post(route('purchases.store'), $payload)
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-004')->firstOrFail();
        $this->assertSame(70_000.0, (float) $purchase->retefuente);
        $this->assertSame(1_930_000.0, (float) $purchase->total_pagar);
    }

    public function test_purchase_explains_which_account_is_missing_when_it_cannot_be_accounted(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->createAccount('1435', 'Mercancías de prueba', 1);
        $bankAccount = $this->createAccount('1110-TEST', 'Bancos de prueba', 1);
        $paymentMethod = PaymentMethod::create(['name' => 'Banco prueba', 'is_editable' => true, 'chart_of_account_id' => $bankAccount->id]);
        $payload = $this->purchasePayload($item, 2_000_000);
        $payload['invoice_number'] = 'DAV-RET-005';
        $payload['payment_method_id'] = $paymentMethod->id;

        $response = $this->post(route('purchases.store'), $payload)
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-005')->firstOrFail();
        $this->assertSame(70_000.0, (float) $purchase->retefuente);
        $this->assertNull($purchase->accounting_voucher_id);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'Falta la cuenta PUC 2365'));
    }

    public function test_expense_line_uses_a_puc_account_without_changing_inventory(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $account = ChartOfAccount::create([
            'code' => '5135-TEST',
            'name' => 'Servicios de prueba',
            'class' => 5,
            'nature' => 'debit',
            'allows_posting' => true,
            'active' => true,
        ]);
        $payload = $this->purchasePayload($item, 200_000);
        $payload['invoice_number'] = 'DAV-GASTO-001';
        $payload['items'] = [[
            'purchase_line_type' => 'gasto',
            'chart_of_account_id' => $account->id,
            'line_description' => 'Servicio de prueba',
            'quantity' => 1,
            'cost_price' => 200_000,
            'iva_percentage' => 0,
            'utility_percentage' => 0,
        ]];

        $this->post(route('purchases.store'), $payload)
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-GASTO-001')->firstOrFail();
        $detail = PurchaseDetail::query()->where('purchase_id', $purchase->id)->firstOrFail();

        $this->assertSame('gasto', $detail->purchase_line_type);
        $this->assertSame($account->id, $detail->chart_of_account_id);
        $this->assertNull($detail->item_id);
        $this->assertSame(10, $item->fresh()->stock);
    }

    public function test_fixed_asset_line_requires_depreciation_data_without_changing_inventory(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $assetAccount = $this->createAccount('1520-TEST', 'Maquinaria de prueba', 1);
        $expenseAccount = $this->createAccount('5160-TEST', 'Depreciación de prueba', 5);
        $accumulatedAccount = $this->createAccount('1592-TEST', 'Depreciación acumulada de prueba', 1, 'credit');
        $payload = $this->purchasePayload($item, 5_000_000);
        $payload['invoice_number'] = 'DAV-ACTIVO-001';
        $payload['items'] = [[
            'purchase_line_type' => 'activo_fijo',
            'chart_of_account_id' => $assetAccount->id,
            'line_description' => 'Máquina de prueba',
            'quantity' => 1,
            'cost_price' => 5_000_000,
            'iva_percentage' => 0,
            'utility_percentage' => 0,
            'useful_life_months' => 60,
            'depreciation_method' => 'straight_line',
            'residual_value' => 500_000,
            'depreciation_expense_account_id' => $expenseAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedAccount->id,
        ]];

        $this->post(route('purchases.store'), $payload)
            ->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-ACTIVO-001')->firstOrFail();
        $detail = PurchaseDetail::query()->where('purchase_id', $purchase->id)->firstOrFail();

        $this->assertSame('activo_fijo', $detail->purchase_line_type);
        $this->assertSame(60, $detail->useful_life_months);
        $this->assertSame(500_000.0, (float) $detail->residual_value);
        $this->assertSame(10, $item->fresh()->stock);
    }

    public function test_show_displays_an_existing_and_a_soft_deleted_purchase(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();

        $this->post(route('purchases.store'), $this->purchasePayload($item, 100_000))
            ->assertRedirect(route('purchases.index'));
        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-001')->firstOrFail();

        $this->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee($purchase->invoice_number);

        $this->delete(route('purchases.destroy', $purchase))
            ->assertRedirect(route('purchases.index'));

        $this->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee($purchase->invoice_number);
    }

    public function test_account_now_posts_a_purchase_that_was_not_accounted_automatically(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $bankAccount = $this->createAccount('1110-TEST', 'Bancos de prueba', 1);
        $this->createAccount('1435', 'Inventario de prueba', 1);

        $this->post(route('purchases.store'), $this->purchasePayload($item, 100_000))
            ->assertRedirect(route('purchases.index'));
        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-001')->firstOrFail();
        $this->assertNull($purchase->accounting_voucher_id);

        $paymentMethod = PaymentMethod::create(['name' => 'Banco prueba', 'is_editable' => true, 'chart_of_account_id' => $bankAccount->id]);
        $purchase->update(['payment_method_id' => $paymentMethod->id]);

        $this->post(route('purchases.account', $purchase))->assertRedirect();

        $this->assertNotNull($purchase->fresh()->accounting_voucher_id);
    }

    public function test_it_posts_to_an_auxiliary_child_account_when_the_root_code_is_a_summary_account(): void
    {
        // Simula el PUC oficial real: el código 1435 existe como cuenta "resumen" (no permite
        // contabilizar directamente, tal como viene en el catálogo estándar colombiano tras
        // importarlo), y la contabilización automática debe usar la cuenta auxiliar hija.
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $bankAccount = $this->createAccount('1110-TEST', 'Bancos de prueba', 1);
        ChartOfAccount::create([
            'code' => '1435',
            'name' => 'Mercancías no fabricadas (resumen)',
            'class' => 1,
            'nature' => 'debit',
            'allows_posting' => false,
            'active' => true,
        ]);
        $auxiliaryInventoryAccount = $this->createAccount('143501', 'Mercancías - auxiliar', 1);

        $paymentMethod = PaymentMethod::create(['name' => 'Banco prueba', 'is_editable' => true, 'chart_of_account_id' => $bankAccount->id]);
        $payload = $this->purchasePayload($item, 100_000);
        $payload['invoice_number'] = 'DAV-AUX-001';
        $payload['payment_method_id'] = $paymentMethod->id;

        $this->post(route('purchases.store'), $payload)->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->where('invoice_number', 'DAV-AUX-001')->firstOrFail();
        $this->assertNotNull($purchase->accounting_voucher_id);

        $voucher = AccountingVoucher::query()->with('lines')->findOrFail($purchase->accounting_voucher_id);
        $this->assertTrue($voucher->lines->contains('chart_of_account_id', $auxiliaryInventoryAccount->id));
    }

    public function test_edit_and_update_change_the_purchase_header_and_line_items(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->post(route('purchases.store'), $this->purchasePayload($item, 100_000))
            ->assertRedirect(route('purchases.index'));
        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-001')->firstOrFail();
        $this->assertSame(11, $item->fresh()->stock);

        $this->get(route('purchases.edit', $purchase))->assertOk()->assertSee('DAV-RET-001');

        $updatePayload = $this->purchasePayload($item, 50_000);
        $updatePayload['invoice_number'] = 'DAV-RET-001-B';
        $updatePayload['provider'] = 'Proveedor actualizado';
        $updatePayload['provider_nit'] = '900999999-1';
        $updatePayload['purchase_date'] = now()->toDateString();
        $updatePayload['items'][0]['quantity'] = 3;

        $this->put(route('purchases.update', $purchase), $updatePayload)
            ->assertRedirect(route('purchases.show', $purchase));

        $purchase->refresh();
        $this->assertSame('DAV-RET-001-B', $purchase->invoice_number);
        $this->assertSame('Proveedor actualizado', $purchase->provider);
        $this->assertSame(1, $purchase->details()->count());
        $this->assertSame(3, $purchase->details()->first()->quantity);
        $this->assertSame(150_000.0, (float) $purchase->subtotal);

        // El stock debe reflejar solo la nueva cantidad (10 + 3 = 13): la entrada original de 1
        // unidad fue revertida antes de aplicar la edición.
        $this->assertSame(13, $item->fresh()->stock);
    }

    public function test_statement_lists_all_purchases_from_the_same_provider(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $payload = $this->purchasePayload($item, 100_000);
        $this->post(route('purchases.store'), $payload)->assertRedirect(route('purchases.index'));
        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-001')->firstOrFail();

        $this->get(route('purchases.statement', $purchase))
            ->assertOk()
            ->assertSee($purchase->provider)
            ->assertSee($purchase->invoice_number);
    }

    public function test_payables_shows_only_providers_with_pending_balance(): void
    {
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->post(route('purchases.store'), $this->purchasePayload($item, 100_000))
            ->assertRedirect(route('purchases.index'));
        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-001')->firstOrFail();

        $response = $this->get(route('purchases.payables'));

        $response->assertOk();
        $response->assertSee($purchase->provider);
        $response->assertSee(route('purchases.statement', $purchase->id));
    }

    public function test_email_sends_the_purchase_invoice(): void
    {
        Mail::fake();
        $this->authenticateWithCompany();
        $item = $this->createProduct();
        $this->post(route('purchases.store'), $this->purchasePayload($item, 100_000))
            ->assertRedirect(route('purchases.index'));
        $purchase = Purchase::query()->where('invoice_number', 'DAV-RET-001')->firstOrFail();

        $this->post(route('purchases.email', $purchase), ['email' => 'proveedor@example.com'])
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
            'code' => 'SKU-RET-001',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => 10,
            'min_stock' => 1,
        ]);
    }

    private function purchasePayload(Item $item, int $costPrice): array
    {
        return [
            'invoice_number' => 'DAV-RET-001',
            'provider' => 'Proveedor de prueba',
            'provider_regimen' => 'gran_contribuyente',
            'withholding_concept' => 'purchase_no_declarante',
            'subtotal' => 1,
            'iva_total' => 0,
            'retefuente' => 0,
            'retention_base' => 0,
            'total_pagar' => 1,
            'items' => [[
                'purchase_line_type' => 'producto',
                'item_id' => $item->id,
                'quantity' => 1,
                'cost_price' => $costPrice,
                'iva_percentage' => 0,
                'utility_percentage' => 0,
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
}
