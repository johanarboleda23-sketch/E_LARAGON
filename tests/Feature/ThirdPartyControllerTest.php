<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ThirdParty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ThirdPartyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('third-parties.index'))->assertRedirect(route('login'));
    }

    public function test_template_can_be_downloaded(): void
    {
        $this->authenticateWithCompany();

        $response = $this->get(route('third-parties.template'));

        $response->assertOk();
        $response->assertSee('Es cliente (si/no)', false);
    }

    public function test_import_creates_and_updates_third_parties_distinguishing_profiles(): void
    {
        $company = $this->authenticateWithCompany();
        ThirdParty::create([
            'company_id' => $company->id,
            'type' => 'natural',
            'name' => 'Nombre viejo',
            'document' => '900111222',
            'is_supplier' => true,
            'active' => true,
        ]);

        $csv = "Tipo;Nombre;Documento;Correo;Telefono;Cliente;Proveedor;Empleado\r\n"
            ."natural;Juan Perez;123456789;juan@example.com;3001234567;si;no;no\r\n"
            ."juridica;Proveedor Actualizado;900111222;ventas@proveedor.com;6012345678;no;si;no\r\n"
            .";;;;;;;\r\n";
        $file = UploadedFile::fake()->createWithContent('terceros.csv', $csv);

        $response = $this->post(route('third-parties.import'), ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseHas('third_parties', ['document' => '123456789', 'name' => 'Juan Perez', 'is_customer' => true, 'is_supplier' => false]);
        $this->assertDatabaseHas('third_parties', ['document' => '900111222', 'name' => 'Proveedor Actualizado', 'is_supplier' => true]);
        $this->assertSame(2, ThirdParty::count());
    }

    public function test_import_rejects_files_with_more_than_500_records(): void
    {
        $this->authenticateWithCompany();
        $rows = ['Tipo;Nombre;Documento;Correo;Telefono;Cliente;Proveedor;Empleado'];
        for ($i = 0; $i < 501; $i++) {
            $rows[] = "natural;Cliente {$i};{$i}00000000;;;si;no;no";
        }
        $file = UploadedFile::fake()->createWithContent('terceros.csv', implode("\r\n", $rows));

        $this->post(route('third-parties.import'), ['file' => $file])->assertStatus(422);
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
