<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\CommercialDocument;
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
            'amount' => 500.01,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(0.0, $purchase->paidAmount());
    }

    public function test_a_saved_credit_note_reduces_the_payment_limit_even_while_it_is_a_draft(): void
    {
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-PAY-NC',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => now()->toDateString(),
            'subtotal' => 1000,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 1000,
        ]);
        CommercialDocument::create([
            'company_id' => $company->id,
            'document_type' => 'customer_credit_note',
            'consecutive' => 'NC-PAY-1',
            'status' => 'draft',
            'third_party_name' => 'Cliente de prueba',
            'document_date' => now()->toDateString(),
            'reference_sale_id' => $sale->id,
            'total' => 300,
        ]);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 701,
            'payment_date' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');

        $this->assertSame(700.0, $sale->fresh()->balanceDue((float) $sale->total));
        $this->assertSame(0.0, $sale->fresh()->paidAmount());
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

    public function test_receipt_payment_is_rejected_when_the_income_period_is_closed(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $company = $this->authenticateWithCompany();
        $sale = Sale::create([
            'company_id' => $company->id,
            'invoice_number' => 'FV-CLOSED-1',
            'customer_name' => 'Cliente de prueba',
            'sale_date' => '2026-10-15',
            'subtotal' => 1000,
            'iva_total' => 0,
            'discount_total' => 0,
            'total' => 1000,
        ]);
        $this->closeOctober($company);

        $this->post(route('sales.payments.store', $sale), [
            'amount' => 100,
            'payment_date' => '2026-10-15',
        ])->assertStatus(409);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1000.0, $sale->fresh()->balanceDue((float) $sale->total));
    }

    public function test_supplier_payment_is_rejected_when_the_expense_period_is_closed(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $company = $this->authenticateWithCompany();
        $purchase = Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-CLOSED-1',
            'provider' => 'Proveedor de prueba',
            'purchase_date' => '2026-10-15',
            'subtotal' => 500,
            'iva_total' => 0,
            'retefuente' => 0,
            'total_pagar' => 500,
        ]);
        $this->closeOctober($company);

        $this->post(route('purchases.payments.store', $purchase), [
            'amount' => 100,
            'payment_date' => '2026-10-15',
        ])->assertStatus(409);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(500.0, $purchase->fresh()->balanceDue((float) $purchase->total_pagar));
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }

    private function closeOctober(Company $company): void
    {
        AccountingPeriod::create([
            'company_id' => $company->id,
            'period_type' => 'month',
            'period_key' => '2026-10',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'status' => 'closed',
            'locked_modules' => ['purchases', 'income', 'expenses', 'bank'],
            'closed_at' => now(),
        ]);
    }
}
