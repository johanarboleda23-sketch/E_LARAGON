<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmployeeContract;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\ThirdParty;
use App\Models\User;
use App\Support\ColombianPayrollRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

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

    private function authenticateWithCompany(): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
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
