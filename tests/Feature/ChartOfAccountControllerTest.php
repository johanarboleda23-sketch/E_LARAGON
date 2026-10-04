<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_csv_import_creates_hierarchy_and_links_parents(): void
    {
        $this->authenticateWithCompany();

        $csv = "Código,Nombre,Categoría,Clase,Relación con,Maneja vencimientos,Diferencia fiscal,Activo,Nivel agrupación\n"
            ."1,Activo,,,,,,,\n"
            ."11,Efectivo y equivalentes de efectivo,,,,,,,\n"
            ."1105,Caja,,,,,,,\n"
            ."110505,Caja general,,,,,,,\n"
            ."11050501,Caja general,Caja - Bancos,Activo,Sin asignar,No maneja vencimiento,No,Sí,Transaccional\n";

        $file = UploadedFile::fake()->createWithContent('puc.csv', $csv);

        $this->post(route('accounting.puc.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('chart_of_accounts', ['code' => '1', 'name' => 'Activo', 'class' => 1, 'nature' => 'debit', 'allows_posting' => false]);
        $leaf = ChartOfAccount::query()->where('code', '11050501')->firstOrFail();
        $this->assertTrue((bool) $leaf->allows_posting);
        $parent = ChartOfAccount::query()->where('code', '110505')->firstOrFail();
        $this->assertSame($parent->id, $leaf->parent_id);
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
