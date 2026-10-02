<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use App\Support\DefaultChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_company_seeds_its_standard_chart_of_accounts(): void
    {
        $this->authenticateWithCompany();

        $this->post(route('admin.companies.store'), ['name' => 'Nueva empresa'])
            ->assertRedirect();

        $company = Company::where('name', 'Nueva empresa')->firstOrFail();
        $this->assertDatabaseHas('chart_of_accounts', ['company_id' => $company->id, 'code' => '2365']);
        $this->assertDatabaseHas('chart_of_accounts', ['company_id' => $company->id, 'code' => '2408']);
    }

    public function test_restoring_the_puc_catalog_does_not_duplicate_existing_accounts(): void
    {
        $company = $this->authenticateWithCompany();
        ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '2365',
            'name' => 'Retención personalizada',
            'class' => 2,
            'nature' => 'credit',
            'allows_posting' => true,
            'active' => true,
        ]);

        $this->post(route('admin.puc.seed'))->assertRedirect();

        $accountCount = ChartOfAccount::withoutGlobalScope('company')->where('company_id', $company->id)->count();
        $this->assertSame(count(DefaultChartOfAccounts::ACCOUNTS), $accountCount);
        $this->assertDatabaseHas('chart_of_accounts', ['company_id' => $company->id, 'code' => '2365', 'name' => 'Retención personalizada']);
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
