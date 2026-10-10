<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\CashRegisterSession;
use App\Models\Company;
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

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }
}
