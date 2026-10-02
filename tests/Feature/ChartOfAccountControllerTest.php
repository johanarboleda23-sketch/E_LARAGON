<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_different_companies_can_each_register_the_same_puc_code(): void
    {
        $companyA = $this->authenticateWithCompany();
        $this->post(route('accounting.puc.store'), $this->accountPayload())
            ->assertRedirect();
        $this->assertDatabaseHas('chart_of_accounts', ['company_id' => $companyA->id, 'code' => '2365']);

        $companyB = $this->authenticateWithCompany();
        $this->post(route('accounting.puc.store'), $this->accountPayload())
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('chart_of_accounts', ['company_id' => $companyB->id, 'code' => '2365']);
    }

    public function test_the_same_company_cannot_register_a_duplicate_puc_code(): void
    {
        $this->authenticateWithCompany();
        $this->post(route('accounting.puc.store'), $this->accountPayload())->assertRedirect();

        $this->post(route('accounting.puc.store'), $this->accountPayload())
            ->assertSessionHasErrors('code');
    }

    private function accountPayload(): array
    {
        return [
            'code' => '2365',
            'name' => 'Retención en la fuente',
            'class' => 2,
            'nature' => 'credit',
            'account_type' => 'auxiliar',
        ];
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
