<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\CashRegisterExpense;
use App\Models\CashRegisterSession;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\DeliveryRoute;
use App\Models\DeliveryStop;
use App\Models\Item;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('cash-register.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_the_cash_register_page(): void
    {
        $this->authenticateWithCompany();

        $this->get(route('cash-register.index'))
            ->assertOk()
            ->assertSeeText('Arqueo y control de caja')
            ->assertSeeText('Apertura de caja')
            ->assertSeeText('Abrir caja');
    }

    public function test_a_session_can_be_opened_and_cannot_be_opened_twice(): void
    {
        $this->authenticateWithCompany();

        $this->post(route('cash-register.open'), [
            'fund_type' => 'general',
            'opening_balance' => 100000,
        ])->assertRedirect();

        $this->assertDatabaseHas('cash_register_sessions', ['fund_type' => 'general', 'status' => 'open']);

        $this->post(route('cash-register.open'), [
            'fund_type' => 'general',
            'opening_balance' => 50000,
        ])->assertStatus(422);

        $this->assertDatabaseCount('cash_register_sessions', 1);
    }

    public function test_closing_computes_expected_cash_from_cash_receipts_and_expenses(): void
    {
        $company = $this->authenticateWithCompany();

        $this->post(route('cash-register.open'), [
            'fund_type' => 'general',
            'opening_balance' => 100000,
        ])->assertRedirect();
        $session = CashRegisterSession::where('status', 'open')->firstOrFail();

        AccountingVoucher::create([
            'company_id' => $company->id,
            'consecutive' => 'RC-1',
            'voucher_type' => 'recibo_caja',
            'voucher_date' => now()->toDateString(),
            'description' => 'Recibo de caja',
            'total_debit' => 50000,
            'total_credit' => 50000,
        ]);
        AccountingVoucher::create([
            'company_id' => $company->id,
            'consecutive' => 'EG-1',
            'voucher_type' => 'egreso',
            'voucher_date' => now()->toDateString(),
            'description' => 'Egreso',
            'total_debit' => 20000,
            'total_credit' => 20000,
        ]);

        // Esperado = 100,000 + 50,000 - 20,000 = 130,000
        $this->post(route('cash-register.close', $session), [
            'counted_cash' => 130000,
        ])->assertRedirect();

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertSame(130000.0, (float) $session->expected_cash);
        $this->assertSame(0.0, (float) $session->difference);
    }

    public function test_a_shortage_is_recorded_as_a_negative_difference(): void
    {
        $this->authenticateWithCompany();

        $this->post(route('cash-register.open'), [
            'fund_type' => 'menor',
            'opening_balance' => 50000,
        ])->assertRedirect();
        $session = CashRegisterSession::where('status', 'open')->firstOrFail();

        $this->post(route('cash-register.close', $session), [
            'counted_cash' => 45000,
        ])->assertRedirect();

        $this->assertSame(-5000.0, (float) $session->fresh()->difference);
    }

    public function test_cash_and_bank_settlements_are_accounted_but_only_cash_increases_expected_cash(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createAccount('1105', 'Caja', $company);
        $this->createAccount('1120', 'Banco', $company);
        $this->createAccount('1305', 'Clientes', $company);
        $route = $this->createRoute();
        $cashSale = $this->createSale('FAC-CASH-001', 60000);
        $bankSale = $this->createSale('FAC-BANK-001', 40000);
        $cashStop = $this->createStop($route, $cashSale);
        $bankStop = $this->createStop($route, $bankSale, 2);
        $session = $this->openSession(100000);

        $this->post(route('cash-register.settle-batch'), [
            'route_id' => $route->id,
            'session_id' => $session->id,
            'stops' => [
                $cashStop->id => ['payment_action' => '1105', 'amount_paid' => 60000],
                $bankStop->id => ['payment_action' => '1120', 'amount_paid' => 40000],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('accounting_vouchers', 2);
        $this->assertSame(160000.0, (float) $session->fresh()->expected_cash);
        $this->assertSame(60000.0, (float) $session->fresh()->cash_in);
    }

    public function test_partial_return_of_an_internal_invoice_restocks_only_the_selected_quantity(): void
    {
        $this->authenticateWithCompany();
        $route = $this->createRoute();
        $item = $this->createProduct(stock: 4);
        $sale = $this->createSale('FAC-RET-001', 200, skipDian: true);
        $detail = $this->createSaleDetail($sale, $item, quantity: 2);
        $stop = $this->createStop($route, $sale);
        $session = $this->openSession(0);

        $this->post(route('cash-register.settle-batch'), [
            'route_id' => $route->id,
            'session_id' => $session->id,
            'stops' => [
                $stop->id => [
                    'payment_action' => 'return_partial',
                    'amount_paid' => 0,
                    'return_quantities' => [$detail->id => 1],
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(5, (int) $item->fresh()->stock);
        $this->assertDatabaseHas('delivery_stop_returns', [
            'delivery_stop_id' => $stop->id,
            'sale_detail_id' => $detail->id,
            'quantity' => 1,
        ]);
        $this->assertSame(200.0, (float) $sale->fresh()->total);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_returns_are_rejected_for_a_sale_not_marked_as_internal(): void
    {
        $this->authenticateWithCompany();
        $route = $this->createRoute();
        $item = $this->createProduct(stock: 4);
        $sale = $this->createSale('FAC-DIAN-001', 100);
        $this->createSaleDetail($sale, $item);
        $stop = $this->createStop($route, $sale);
        $session = $this->openSession(0);

        $this->post(route('cash-register.settle-batch'), [
            'route_id' => $route->id,
            'session_id' => $session->id,
            'stops' => [$stop->id => ['payment_action' => 'return_total', 'amount_paid' => 0]],
        ])->assertStatus(422);

        $this->assertSame(4, (int) $item->fresh()->stock);
        $this->assertDatabaseCount('delivery_stop_returns', 0);
    }

    public function test_product_exchange_updates_both_stocks_and_accounts_for_the_price_difference(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createAccount('1105', 'Caja', $company);
        $this->createAccount('4135', 'Ingresos', $company);
        $route = $this->createRoute();
        $returnedItem = $this->createProduct(stock: 4);
        $sale = $this->createSale('FAC-EXC-001', 100, skipDian: true);
        $detail = $this->createSaleDetail($sale, $returnedItem);
        $stop = $this->createStop($route, $sale);
        $replacementItem = Item::create([
            'type' => 'producto',
            'name' => 'Producto de reemplazo',
            'code' => 'SKU-ARQ-REPLACE',
            'sale_price' => 120,
            'purchase_price' => 60,
            'stock' => 5,
            'min_stock' => 1,
        ]);
        $session = $this->openSession(500);

        $this->post(route('cash-register.settle-batch'), [
            'route_id' => $route->id,
            'session_id' => $session->id,
            'stops' => [
                $stop->id => [
                    'payment_action' => 'exchange',
                    'amount_paid' => 0,
                    'collection_method' => '1105',
                    'return_quantities' => [$detail->id => 1],
                    'replacement_item_id' => $replacementItem->id,
                    'replacement_quantity' => 1,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(5, (int) $returnedItem->fresh()->stock);
        $this->assertSame(4, (int) $replacementItem->fresh()->stock);
        $this->assertSame(520.0, (float) $session->fresh()->expected_cash);
        $this->assertDatabaseHas('cash_register_movements', [
            'delivery_stop_id' => $stop->id,
            'movement_type' => 'exchange_difference',
            'amount' => 20,
        ]);
    }

    public function test_cash_register_page_renders_the_selected_route_invoice_actions(): void
    {
        $this->authenticateWithCompany();
        $route = $this->createRoute();
        $sale = $this->createSale('FAC-VIEW-001', 100);
        $this->createStop($route, $sale);
        $this->openSession(1000);

        $this->get(route('cash-register.index', ['route_id' => $route->id]))
            ->assertOk()
            ->assertSee('FAC-VIEW-001')
            ->assertSee('DEVOLUCIÓN TOTAL')
            ->assertSee('Efectivo esperado');
    }

    public function test_invoice_can_be_attached_to_a_route_without_mutating_inventory(): void
    {
        $this->authenticateWithCompany();
        $route = $this->createRoute();
        $sale = $this->createSale('FAC-ATTACH-001', 100);
        $item = $this->createProduct(stock: 3);
        $this->createSaleDetail($sale, $item);

        $this->post(route('cash-register.attach-invoice', $route), ['sale_id' => $sale->id])
            ->assertRedirect();

        $this->assertDatabaseHas('delivery_stops', [
            'delivery_route_id' => $route->id,
            'sale_id' => $sale->id,
        ]);
        $this->assertSame(3, (int) $item->fresh()->stock);
    }

    public function test_receivable_collection_posts_payment_without_changing_inventory(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createAccount('1105', 'Caja', $company);
        $this->createAccount('1305', 'Clientes', $company);
        $item = $this->createProduct(stock: 4);
        $sale = $this->createSale('FAC-CART-001', 100);
        $this->createSaleDetail($sale, $item);
        $session = $this->openSession(500);

        $this->post(route('cash-register.collect-receivable'), [
            'sale_id' => $sale->id,
            'session_id' => $session->id,
            'amount' => 40,
            'payment_method' => '1105',
        ])->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'amount' => 40,
            'method' => '1105',
        ]);
        $this->assertSame(4, (int) $item->fresh()->stock);
        $this->assertSame(540.0, (float) $session->fresh()->expected_cash);
    }

    public function test_cash_expense_is_accounted_and_reduces_expected_cash(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createAccount('1105', 'Caja', $company);
        $this->createAccount('5135', 'Combustible', $company);
        $session = $this->openSession(100000);

        $this->post(route('cash-register.expenses.store', $session), [
            'category' => 'combustible',
            'concept' => 'Combustible ruta',
            'amount' => 25000,
        ])->assertRedirect();

        $this->assertDatabaseHas('cash_register_expenses', [
            'cash_register_session_id' => $session->id,
            'category' => 'combustible',
            'amount' => 25000,
        ]);
        $expense = CashRegisterExpense::query()
            ->where('cash_register_session_id', $session->id)
            ->firstOrFail();
        $this->assertSame(now()->toDateString(), $expense->expense_date->toDateString());
        $this->assertSame(75000.0, (float) $session->fresh()->expected_cash);
        $this->assertSame(25000.0, (float) $session->fresh()->cash_out);
    }

    public function test_batch_settlement_records_the_expense_date_and_posts_its_voucher(): void
    {
        $company = $this->authenticateWithCompany();
        $this->createAccount('1105', 'Caja', $company);
        $this->createAccount('5195', 'Gastos diversos', $company);
        $route = $this->createRoute();
        $session = $this->openSession(500);

        $this->post(route('cash-register.settle-batch'), [
            'route_id' => $route->id,
            'session_id' => $session->id,
            'expenses' => [
                ['category' => 'otro', 'concept' => 'Gasto de prueba', 'amount' => 100],
            ],
        ])->assertRedirect(route('cash-register.index', [
            'fund_type' => 'general',
            'route_id' => $route->id,
        ]));

        $this->assertDatabaseHas('cash_register_expenses', [
            'cash_register_session_id' => $session->id,
            'category' => 'otro',
            'amount' => 100,
        ]);
        $expense = CashRegisterExpense::query()
            ->where('cash_register_session_id', $session->id)
            ->firstOrFail();
        $this->assertSame(now()->toDateString(), $expense->expense_date->toDateString());
        $this->assertDatabaseCount('accounting_vouchers', 1);
        $this->assertSame(400.0, (float) $session->fresh()->expected_cash);
    }

    public function test_closing_uses_the_sum_of_entered_cash_denominations(): void
    {
        $this->authenticateWithCompany();
        $session = $this->openSession(0);

        $this->post(route('cash-register.close', $session), [
            'counted_cash' => 0,
            'bill_50000' => 1,
            'coin_500' => 2,
        ])->assertRedirect();

        $this->assertSame(51000.0, (float) $session->fresh()->counted_cash);
        $this->assertSame(51000.0, (float) $session->fresh()->difference);
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function createAccount(string $code, string $name, Company $company): ChartOfAccount
    {
        return ChartOfAccount::firstOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'name' => $name,
                'class' => (int) substr($code, 0, 1),
                'nature' => in_array(substr($code, 0, 1), ['1', '5'], true) ? 'debit' : 'credit',
                'allows_posting' => true,
                'active' => true,
            ],
        );
    }

    private function createRoute(): DeliveryRoute
    {
        return DeliveryRoute::create([
            'code' => 'RUTA-ARQ',
            'vehicle_plate' => 'ABC123',
            'driver_name' => 'Conductor de prueba',
            'route_date' => now()->toDateString(),
            'status' => 'planned',
        ]);
    }

    private function createStop(DeliveryRoute $route, Sale $sale, int $sequence = 1): DeliveryStop
    {
        return DeliveryStop::create([
            'delivery_route_id' => $route->id,
            'sale_id' => $sale->id,
            'sequence' => $sequence,
            'customer_name' => $sale->customer_name,
            'address' => 'Calle de prueba',
            'difficulty_score' => 50,
            'status' => 'pending',
        ]);
    }

    private function createSale(string $invoiceNumber, float $total, bool $skipDian = false): Sale
    {
        return Sale::create([
            'invoice_number' => $invoiceNumber,
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => $total,
            'iva_total' => 0,
            'discount_total' => 0,
            'withholding_concept' => 'none',
            'retention_base' => 0,
            'retention_total' => 0,
            'total' => $total,
            'skip_dian' => $skipDian,
        ]);
    }

    private function createProduct(int $stock): Item
    {
        return Item::create([
            'type' => 'producto',
            'name' => 'Producto de arqueo',
            'code' => 'SKU-ARQ',
            'sale_price' => 100,
            'purchase_price' => 50,
            'stock' => $stock,
            'min_stock' => 1,
        ]);
    }

    private function createSaleDetail(Sale $sale, Item $item, int $quantity = 1): SaleDetail
    {
        return SaleDetail::create([
            'sale_id' => $sale->id,
            'item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => 100,
            'iva_percentage' => 0,
            'discount_percentage' => 0,
            'line_total' => $quantity * 100,
        ]);
    }

    private function openSession(float $openingBalance): CashRegisterSession
    {
        $this->post(route('cash-register.open'), [
            'fund_type' => 'general',
            'opening_balance' => $openingBalance,
        ])->assertRedirect();

        return CashRegisterSession::query()->where('status', 'open')->firstOrFail();
    }
}
