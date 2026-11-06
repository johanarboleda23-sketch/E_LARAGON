<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountingVoucher;
use App\Models\BankStatement;
use App\Models\CashRegisterMovement;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodClosingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_a_month_locks_the_selected_modules_and_records_the_audit_event(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company, $admin] = $this->authenticateWithCompany('admin');

        $this->post(route('period-closing.close-month'), ['year' => 2026, 'month' => 10])
            ->assertRedirect(route('period-closing.index', ['year' => 2026]))
            ->assertSessionHas('success');

        $period = AccountingPeriod::query()->where('period_key', '2026-10')->firstOrFail();
        $this->assertSame('closed', $period->status);
        $this->assertSame(['purchases', 'income', 'expenses', 'bank'], $period->locked_modules);
        $this->assertSame($admin->id, $period->closed_by);
        $this->assertDatabaseHas('accounting_period_events', [
            'accounting_period_id' => $period->id,
            'action' => 'close',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_month_cannot_close_while_a_bank_movement_is_unreconciled(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');
        BankStatement::create([
            'company_id' => $company->id,
            'transaction_date' => '2026-10-12',
            'transaction_type' => 'debit',
            'amount' => 250,
            'status' => 'pending',
        ]);

        $this->post(route('period-closing.close-month'), ['year' => 2026, 'month' => 10])
            ->assertUnprocessable()
            ->assertSeeText('No se puede cerrar el mes mientras tenga movimientos bancarios pendientes de conciliar.');

        $this->assertDatabaseMissing('accounting_periods', [
            'period_type' => 'month',
            'period_key' => '2026-10',
        ]);
    }

    public function test_month_cannot_close_while_a_cash_register_session_is_open(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');
        CashRegisterSession::create([
            'company_id' => $company->id,
            'fund_type' => 'general',
            'status' => 'open',
            'opened_at' => '2026-10-20 08:00:00',
            'opening_balance' => 100,
        ]);

        $this->post(route('period-closing.close-month'), ['year' => 2026, 'month' => 10])
            ->assertUnprocessable()
            ->assertSeeText('No se puede cerrar el mes mientras haya sesiones de caja abiertas.');

        $this->assertDatabaseMissing('accounting_periods', [
            'period_type' => 'month',
            'period_key' => '2026-10',
        ]);
    }

    public function test_monthly_income_summary_includes_cash_register_receipts_without_double_counting_the_payment(): void
    {
        $this->createCashRegisterReceipt();

        $this->get(route('period-closing.index', ['year' => 2026]))
            ->assertViewHas('months', fn (array $months): bool => $months[9]['summary']['income']['count'] === 1
                && $months[9]['summary']['income']['total'] === 500.0);
    }

    public function test_income_details_include_the_cash_register_receipt_instead_of_its_duplicate_payment(): void
    {
        $voucher = $this->createCashRegisterReceipt();

        $this->get(route('period-closing.index', ['year' => 2026, 'month' => 10, 'detail' => 'income']))
            ->assertViewHas('detailRecords', fn ($records): bool => $records->getCollection()->contains('id', $voucher->id))
            ->assertViewHas('paymentRecords', fn ($records): bool => $records->total() === 0);
    }

    public function test_non_administrator_cannot_reopen_a_period(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');
        $period = $this->closedMonth($company, 2026, 10);
        $accountant = User::factory()->create();
        $company->users()->attach($accountant->id, ['role' => 'contador']);

        $this->actingAs($accountant)->withSession(['company_id' => $company->id])
            ->post(route('period-closing.reopen', $period), ['reason' => 'Corregir factura'])
            ->assertForbidden();
    }

    public function test_administrator_can_reopen_a_period_and_the_reason_is_audited(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company, $admin] = $this->authenticateWithCompany('admin');
        $period = $this->closedMonth($company, 2026, 10);

        $this->actingAs($admin)->withSession(['company_id' => $company->id])
            ->post(route('period-closing.reopen', $period), ['reason' => 'Corregir factura de compra'])
            ->assertRedirect(route('period-closing.index', ['year' => 2026]));

        $this->assertSame('open', $period->fresh()->status);
        $this->assertDatabaseHas('accounting_period_events', [
            'accounting_period_id' => $period->id,
            'action' => 'reopen',
            'actor_id' => $admin->id,
            'reason' => 'Corregir factura de compra',
        ]);
    }

    public function test_administrator_must_provide_a_reason_to_reopen_a_period(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');
        $period = $this->closedMonth($company, 2026, 10);

        $this->post(route('period-closing.reopen', $period), [])
            ->assertSessionHasErrors('reason');

        $this->assertSame('closed', $period->fresh()->status);
    }

    public function test_month_cannot_be_reopened_while_its_year_is_closed(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');
        $period = $this->closedMonth($company, 2025, 10);
        AccountingPeriod::create([
            'company_id' => $company->id,
            'period_type' => 'year',
            'period_key' => '2025',
            'period_start' => '2025-01-01',
            'period_end' => '2025-12-31',
            'status' => 'closed',
            'locked_modules' => ['purchases', 'income', 'expenses', 'bank'],
            'closed_at' => now(),
        ]);

        $this->post(route('period-closing.reopen', $period), ['reason' => 'Corregir compra'])
            ->assertStatus(409);

        $this->assertSame('closed', $period->fresh()->status);
    }

    public function test_year_close_requires_all_twelve_months_to_be_closed(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');

        $this->post(route('period-closing.close-year'), ['year' => 2025])
            ->assertUnprocessable()
            ->assertSeeText('Debes cerrar los doce meses antes de cerrar el año.');
    }

    public function test_year_can_be_closed_after_all_twelve_months_are_closed(): void
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');

        foreach (range(1, 12) as $month) {
            $start = now()->setDate(2025, $month, 1)->startOfMonth();
            AccountingPeriod::create([
                'company_id' => $company->id,
                'period_type' => 'month',
                'period_key' => $start->format('Y-m'),
                'period_start' => $start->toDateString(),
                'period_end' => $start->copy()->endOfMonth()->toDateString(),
                'status' => 'closed',
                'locked_modules' => ['purchases', 'income', 'expenses', 'bank'],
                'closed_at' => now(),
            ]);
        }

        $this->post(route('period-closing.close-year'), ['year' => 2025])
            ->assertRedirect(route('period-closing.index', ['year' => 2025]))
            ->assertSessionHas('success');

        $this->assertSame('closed', AccountingPeriod::query()->where('period_type', 'year')->where('period_key', '2025')->firstOrFail()->status);
    }

    private function authenticateWithCompany(string $role): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['role' => $role]);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return [$company, $user];
    }

    private function closedMonth(Company $company, int $year, int $month): AccountingPeriod
    {
        $start = now()->setDate($year, $month, 1)->startOfMonth();

        return AccountingPeriod::create([
            'company_id' => $company->id,
            'period_type' => 'month',
            'period_key' => $start->format('Y-m'),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'status' => 'closed',
            'locked_modules' => ['purchases', 'income', 'expenses', 'bank'],
            'closed_at' => now(),
        ]);
    }

    private function createCashRegisterReceipt(): AccountingVoucher
    {
        $this->travelTo('2026-11-05 12:00:00');
        [$company] = $this->authenticateWithCompany('admin');
        $session = CashRegisterSession::create([
            'company_id' => $company->id,
            'fund_type' => 'general',
            'status' => 'closed',
            'opened_at' => '2026-10-31 08:00:00',
            'opening_balance' => 100,
        ]);
        $voucher = AccountingVoucher::create([
            'company_id' => $company->id,
            'voucher_type' => 'recibo_caja',
            'consecutive' => 'RC-REPORT-1',
            'voucher_date' => '2026-10-31',
            'total_debit' => 500,
            'total_credit' => 500,
        ]);
        $payment = Payment::create([
            'company_id' => $company->id,
            'payable_type' => (new Sale)->getMorphClass(),
            'payable_id' => 1,
            'amount' => 500,
            'payment_date' => '2026-10-31',
            'method' => '1105',
        ]);
        CashRegisterMovement::create([
            'company_id' => $company->id,
            'cash_register_session_id' => $session->id,
            'payment_id' => $payment->id,
            'accounting_voucher_id' => $voucher->id,
            'movement_type' => 'collection',
            'amount' => 500,
        ]);

        return $voucher;
    }
}
