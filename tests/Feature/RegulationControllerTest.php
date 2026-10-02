<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegulationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_regulation_center_shows_disclaimer_and_results(): void
    {
        $this->authenticateWithCompany();

        $this->get(route('regulations.index'))
            ->assertOk()
            ->assertSeeText('no constituye asesoría legal')
            ->assertSeeText('NIIF');
    }

    public function test_regulation_center_filters_by_keyword(): void
    {
        $this->authenticateWithCompany();

        $response = $this->get(route('regulations.index', ['q' => 'horario laboral']));

        $response->assertOk()
            ->assertSeeText('Jornada laboral')
            ->assertDontSeeText('NIIF');
    }

    public function test_regulation_center_filters_by_sector(): void
    {
        $this->authenticateWithCompany();

        $response = $this->get(route('regulations.index', ['sector' => 'seguridad_social']));

        $response->assertOk()
            ->assertSeeText('PILA')
            ->assertDontSeeText('NIIF');
    }

    public function test_consulta_role_can_read_the_regulation_center(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'consulta']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->get(route('regulations.index'))->assertOk();
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
