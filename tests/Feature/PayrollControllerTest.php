<?php

namespace Tests\Feature;

use App\Models\AccountingVoucher;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\EmployeeContract;
use App\Models\FactusCredential;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\ThirdParty;
use App\Models\User;
use App\Support\ColombianPayrollRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_the_social_security_operator_link_when_configured(): void
    {
        $company = $this->authenticateWithCompany();
        $company->update([
            'social_security_operator_name' => 'Enlace Operativo',
            'social_security_operator_url' => 'https://www.enlaceoperativo.com',
        ]);

        $this->get(route('payroll.index'))
            ->assertOk()
            ->assertSee('https://www.enlaceoperativo.com');
    }

    public function test_index_renders_with_and_without_existing_runs(): void
    {
        $this->authenticateWithCompany();

        $this->get(route('payroll.index'))->assertOk();

        $employee = $this->createEmployee();
        $this->createContract($employee, riskClass: 1, epsName: 'Sura EPS', afpName: 'Protección', compensationFundName: 'Comfama');
        $this->post(route('payroll.calculate'), [
            'period' => '2026-10',
            'payment_date' => '2026-10-31',
            'employees' => [
                ['third_party_id' => $employee->id, 'salary' => 2000000.0],
            ],
        ])->assertSessionHasNoErrors();

        $this->get(route('payroll.index'))
            ->assertOk()
            ->assertSee('2026-10');
    }

    public function test_calculate_derives_arl_and_parafiscals_from_the_employee_contract(): void
    {
        $this->authenticateWithCompany();
        $employee = $this->createEmployee();
        $this->createContract($employee, riskClass: 3, epsName: 'Sura EPS', afpName: 'Protección', compensationFundName: 'Comfama');

        $salary = 2000000.0;
        $this->post(route('payroll.calculate'), [
            'period' => '2026-10',
            'payment_date' => '2026-10-31',
            'employees' => [
                ['third_party_id' => $employee->id, 'salary' => $salary],
            ],
        ])->assertSessionHasNoErrors();

        $line = PayrollLine::query()->firstOrFail();

        $expectedArl = round($salary * ColombianPayrollRates::arlRate(3), 2);
        $expectedSena = round($salary * ColombianPayrollRates::SENA_RATE, 2);
        $expectedIcbf = round($salary * ColombianPayrollRates::ICBF_RATE, 2);
        $expectedCompensationFund = round($salary * ColombianPayrollRates::COMPENSATION_FUND_RATE, 2);

        $this->assertSame(3, $line->arl_risk_class);
        $this->assertEquals($expectedArl, (float) $line->arl_employer);
        $this->assertEquals($expectedSena, (float) $line->sena);
        $this->assertEquals($expectedIcbf, (float) $line->icbf);
        $this->assertEquals($expectedCompensationFund, (float) $line->compensation_fund);
        $this->assertEquals($expectedSena + $expectedIcbf + $expectedCompensationFund, (float) $line->parafiscals);
        $this->assertSame('Sura EPS', $line->eps_name);
        $this->assertSame('Protección', $line->afp_name);
        $this->assertSame('Comfama', $line->compensation_fund_name);
    }

    public function test_higher_risk_classes_produce_a_higher_arl_contribution(): void
    {
        $this->authenticateWithCompany();
        $lowRiskEmployee = $this->createEmployee('11111111');
        $this->createContract($lowRiskEmployee, riskClass: 1);
        $highRiskEmployee = $this->createEmployee('22222222');
        $this->createContract($highRiskEmployee, riskClass: 5);

        $salary = 1500000.0;
        $this->post(route('payroll.calculate'), [
            'period' => '2026-10',
            'payment_date' => '2026-10-31',
            'employees' => [
                ['third_party_id' => $lowRiskEmployee->id, 'salary' => $salary],
                ['third_party_id' => $highRiskEmployee->id, 'salary' => $salary],
            ],
        ])->assertSessionHasNoErrors();

        $lowRiskLine = PayrollLine::where('third_party_id', $lowRiskEmployee->id)->firstOrFail();
        $highRiskLine = PayrollLine::where('third_party_id', $highRiskEmployee->id)->firstOrFail();

        $this->assertTrue((float) $highRiskLine->arl_employer > (float) $lowRiskLine->arl_employer);
    }

    public function test_social_security_file_contains_the_real_affiliation_data_instead_of_a_placeholder(): void
    {
        $this->authenticateWithCompany();
        $employee = $this->createEmployee();
        $this->createContract($employee, riskClass: 2, epsName: 'Sura EPS', afpName: 'Protección', compensationFundName: 'Comfama');

        $this->post(route('payroll.calculate'), [
            'period' => '2026-10',
            'payment_date' => '2026-10-31',
            'employees' => [
                ['third_party_id' => $employee->id, 'salary' => 1800000],
            ],
        ])->assertSessionHasNoErrors();

        $run = PayrollRun::query()->firstOrFail();

        $response = $this->get(route('payroll.social-security.file', $run));
        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('Sura EPS', $content);
        $this->assertStringContainsString('Protección', $content);
        $this->assertStringContainsString('Comfama', $content);
        $this->assertStringContainsString('Completa', $content);
        $this->assertStringNotContainsString('Completar EPS, AFP, ARL, caja y novedades', $content);
    }

    public function test_payroll_run_is_accounted_automatically_with_balanced_debits_and_credits(): void
    {
        $this->authenticateWithCompany();
        $employee = $this->createEmployee();
        $this->createContract($employee, riskClass: 1);
        $this->createAccount('5105', 'Gastos de personal', 5);
        $this->createAccount('5130', 'Prestaciones sociales gasto', 5);
        $this->createAccount('5135', 'Aportes patronales gasto', 5);
        $this->createAccount('2370', 'Retención por pagar', 2, 'credit');
        $this->createAccount('2380', 'Aportes de seguridad social por pagar', 2, 'credit');
        $this->createAccount('2610', 'Prestaciones sociales por pagar', 2, 'credit');
        $this->createAccount('2505', 'Salarios por pagar', 2, 'credit');

        $this->post(route('payroll.calculate'), [
            'period' => '2026-10',
            'payment_date' => '2026-10-31',
            'employees' => [
                ['third_party_id' => $employee->id, 'salary' => 1500000],
            ],
        ])->assertSessionHasNoErrors();

        $run = PayrollRun::query()->firstOrFail();
        $this->assertNotNull($run->accounting_voucher_id);

        $voucher = AccountingVoucher::query()->with('lines')->findOrFail($run->accounting_voucher_id);
        $this->assertSame((float) $voucher->total_debit, (float) $voucher->total_credit);
    }

    public function test_payroll_run_is_sent_to_factus_when_credentials_are_configured(): void
    {
        $company = $this->authenticateWithCompany();
        $employee = $this->createEmployee();
        $this->createContract($employee, riskClass: 1);
        $this->createFactusCredential($company);
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 600]),
            '*/v2/payrolls' => Http::response(['data' => ['bill' => ['number' => 'NO-1', 'cufe' => 'cune-abc']]]),
        ]);

        $this->post(route('payroll.calculate'), [
            'period' => '2026-10',
            'payment_date' => '2026-10-31',
            'employees' => [
                ['third_party_id' => $employee->id, 'salary' => 1500000],
            ],
        ])->assertSessionHasNoErrors();

        $line = PayrollLine::query()->firstOrFail();
        $this->assertSame('enviada', $line->factus_status);
        $this->assertSame('cune-abc', $line->factus_cufe);
    }

    public function test_payroll_run_skips_factus_when_no_credentials_are_configured(): void
    {
        $this->authenticateWithCompany();
        $employee = $this->createEmployee();
        $this->createContract($employee, riskClass: 1);
        Http::fake();

        $response = $this->post(route('payroll.calculate'), [
            'period' => '2026-10',
            'payment_date' => '2026-10-31',
            'employees' => [
                ['third_party_id' => $employee->id, 'salary' => 1500000],
            ],
        ])->assertSessionHasNoErrors();

        $line = PayrollLine::query()->firstOrFail();
        $this->assertNull($line->factus_status);
        $response->assertSessionHas('success', fn (string $message) => str_contains($message, 'No se envió a la DIAN'));
        Http::assertNothingSent();
    }

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
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
            'payroll_numbering_range_id' => 7,
            'active' => true,
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

    private function createEmployee(string $document = '900123456'): ThirdParty
    {
        return ThirdParty::create([
            'type' => 'natural',
            'name' => 'Empleado de prueba',
            'document' => $document,
            'email' => 'empleado@example.com',
            'is_customer' => false,
            'is_supplier' => false,
            'is_employee' => true,
            'active' => true,
        ]);
    }

    private function createContract(ThirdParty $employee, int $riskClass, ?string $epsName = null, ?string $afpName = null, ?string $compensationFundName = null): EmployeeContract
    {
        return EmployeeContract::create([
            'third_party_id' => $employee->id,
            'consecutive' => 'CONT-'.$employee->id,
            'employee_initial' => 'E',
            'start_date' => '2026-01-01',
            'salary' => 1500000,
            'contract_type' => 'indefinido',
            'active' => true,
            'arl_risk_class' => $riskClass,
            'eps_name' => $epsName,
            'afp_name' => $afpName,
            'compensation_fund_name' => $compensationFundName,
        ]);
    }
}
