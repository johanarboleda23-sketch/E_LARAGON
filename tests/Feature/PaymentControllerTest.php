<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_partial_payment_reduces_the_sale_balance_without_marking_it_fully_paid(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-PAY-1',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 1000,
        ]);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 400,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(400.0, $sale->paidAmount());
        $this->assertSame(600.0, $sale->balanceDue((float) $sale->total));

        $this->get(route('sales.statement', $sale))
            ->assertOk()
            ->assertSee('Abono parcial');
    }

    public function test_a_payment_cannot_exceed_the_pending_balance(): void
    {
        $company = $this->authenticateWithCompany();
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-PAY-1',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => now()->toDateString(),
            'subtotal' => 500,
            'iva_total' => 0,
            'retefuente' => 0,
            'total_pagar' => 500,
        ]);

        $this->post(route('purchases.payments.store', $purchase), [
            'amount' => 600,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0.0, $purchase->paidAmount());
    }

    public function test_fully_paying_a_document_marks_it_as_paid_in_the_statement(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-PAY-2',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 200,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 200,
        ]);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 200,
            'payment_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->get(route('sales.statement', $sale))->assertOk()->assertSee('Pagada');
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
