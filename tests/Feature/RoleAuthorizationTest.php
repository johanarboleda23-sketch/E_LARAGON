<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_can_write_to_sales_but_not_to_purchases(): void
    {
        $this->authenticateWithRole('vendedor');

        $this->post(route('sales.store'), [])->assertSessionHasErrors();
        $this->post(route('purchases.store'), [])->assertStatus(403);
    }

    public function test_consulta_role_cannot_write_to_any_module(): void
    {
        $this->authenticateWithRole('consulta');

        $this->post(route('accounting.puc.store'), [])->assertStatus(403);
        $this->post(route('third-parties.store'), [])->assertStatus(403);
    }

    public function test_consulta_role_can_still_read_modules(): void
    {
        $this->authenticateWithRole('consulta');

        $this->get(route('third-parties.index'))->assertOk();
    }

    public function test_contador_can_write_to_puc_but_not_to_sales(): void
    {
        $this->authenticateWithRole('contador');

        $this->post(route('accounting.puc.store'), [])->assertSessionHasErrors();
        $this->post(route('sales.index'), [])->assertStatus(403);
    }

    private function authenticateWithRole(string $role): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => $role]);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }
}
