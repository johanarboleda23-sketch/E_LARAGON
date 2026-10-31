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

    public function test_guest_cannot_upload_a_rut_pdf(): void
    {
        $this->post(route('third-parties.rut.extract'))->assertRedirect(route('login'));
    }

    public function test_template_can_be_downloaded(): void
    {
        $this->authenticateWithCompany();

        $response = $this->get(route('third-parties.template'));

        $response->assertOk();
        $response->assertSee('Es cliente (si/no)', false);
    }

    public function test_index_displays_edit_and_rut_autocomplete_actions(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'type' => 'natural',
            'name' => 'Ana Pérez',
            'document' => '123456789',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Bogotá',
            'neighborhood' => 'Centro',
            'is_customer' => true,
        ]);

        $response = $this->get(route('third-parties.index'));

        $response->assertSee('Editar')
            ->assertSee('Autocompletar desde PDF del RUT')
            ->assertSee('Calle 10 # 20-30')
            ->assertSee(route('third-parties.update', $thirdParty));
    }

    public function test_third_party_can_be_created_with_address_details(): void
    {
        $company = $this->authenticateWithCompany();

        $response = $this->post(route('third-parties.store'), [
            'type' => 'natural',
            'name' => 'Ana Pérez',
            'document' => '123456789',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Bogotá',
            'neighborhood' => 'Centro',
            'is_customer' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('third_parties', [
            'company_id' => $company->id,
            'name' => 'Ana Pérez',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Bogotá',
            'neighborhood' => 'Centro',
        ]);
    }

    public function test_third_party_can_be_updated_with_address_details(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'type' => 'natural',
            'name' => 'Nombre anterior',
            'document' => '123456789',
            'is_customer' => true,
            'active' => true,
        ]);

        $response = $this->put(route('third-parties.update', $thirdParty), [
            'type' => 'juridica',
            'name' => 'Comercial Nuevo SAS',
            'document' => '900123456',
            'email' => 'contacto@example.com',
            'phone' => '3001234567',
            'address' => 'Carrera 10 # 20-30',
            'city' => 'Medellín',
            'neighborhood' => 'Laureles',
            'is_supplier' => '1',
            'active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('third_parties', [
            'id' => $thirdParty->id,
            'type' => 'juridica',
            'name' => 'Comercial Nuevo SAS',
            'document' => '900123456',
            'email' => 'contacto@example.com',
            'address' => 'Carrera 10 # 20-30',
            'city' => 'Medellín',
            'neighborhood' => 'Laureles',
            'is_customer' => false,
            'is_supplier' => true,
            'active' => true,
        ]);
    }

    public function test_third_party_from_another_company_cannot_be_updated(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $thirdParty = ThirdParty::create([
            'company_id' => $otherCompany->id,
            'type' => 'natural',
            'name' => 'Tercero protegido',
            'document' => '555123',
            'is_customer' => true,
            'active' => true,
        ]);

        $response = $this->put(route('third-parties.update', $thirdParty), [
            'type' => 'natural',
            'name' => 'Nombre no autorizado',
            'active' => '1',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('third_parties', [
            'id' => $thirdParty->id,
            'name' => 'Tercero protegido',
        ]);
    }

    public function test_rut_pdf_is_parsed_locally_and_returns_prefill_data(): void
    {
        $this->authenticateWithCompany();
        $file = UploadedFile::fake()->createWithContent('rut.pdf', $this->rutPdfContent());

        $response = $this->postJson(route('third-parties.rut.extract'), ['rut_pdf' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Comercial Nuevo SAS')
            ->assertJsonPath('data.document', '900123456')
            ->assertJsonPath('data.type', 'juridica')
            ->assertJsonPath('data.address', 'Carrera 10 # 20-30')
            ->assertJsonPath('data.city', 'BOGOTA D.C.')
            ->assertJsonPath('data.email', 'contacto@example.com');
    }

    public function test_rut_pdf_assembles_a_natural_person_name(): void
    {
        $this->authenticateWithCompany();
        $file = UploadedFile::fake()->createWithContent('rut.pdf', $this->rutPdfContent([
            '5. Numero de Identificacion Tributaria (NIT)',
            '123456789',
            '6. DV',
            '0',
            '31. Primer apellido',
            'Perez',
            '32. Segundo apellido',
            'Gomez',
            '33. Primer nombre',
            'Ana',
            '34. Otros nombres',
            'Maria',
            '35. Razon social',
            '36. Nombre comercial',
            '40. Ciudad/Municipio',
            '11001 BOGOTA D.C.',
            '41. Direccion principal',
            'Calle 10 # 20-30',
            '42. Correo electronico',
            'ana@example.com',
            '43. Codigo postal',
        ]));

        $response = $this->postJson(route('third-parties.rut.extract'), ['rut_pdf' => $file]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Perez Gomez Ana Maria')
            ->assertJsonPath('data.document', '123456789')
            ->assertJsonPath('data.type', 'natural');
    }

    public function test_unreadable_rut_pdf_returns_a_validation_error(): void
    {
        $this->authenticateWithCompany();
        $file = UploadedFile::fake()->createWithContent('rut.pdf', 'not a PDF');

        $response = $this->postJson(route('third-parties.rut.extract'), ['rut_pdf' => $file]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.rut_pdf.0', 'No fue posible leer el PDF. Verifica que no esté protegido o dañado.');
    }

    public function test_rut_extraction_rejects_files_that_are_not_pdfs(): void
    {
        $this->authenticateWithCompany();
        $file = UploadedFile::fake()->createWithContent('documento.txt', 'texto sin formato PDF');

        $response = $this->postJson(route('third-parties.rut.extract'), ['rut_pdf' => $file]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('rut_pdf');
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

    private function rutPdfContent(?array $lines = null): string
    {
        $lines ??= [
            '5. Numero de Identificacion Tributaria (NIT)',
            '900123456',
            '6. DV',
            '7',
            '35. Razon social',
            'Comercial Nuevo SAS',
            '36. Nombre comercial',
            'Comercial Nuevo',
            '40. Ciudad/Municipio',
            '11001 BOGOTA D.C.',
            '41. Direccion principal',
            'Carrera 10 # 20-30',
            '42. Correo electronico',
            'contacto@example.com',
            '43. Codigo postal',
        ];
        $text = 'BT /F1 9 Tf 50 780 Td ';

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $text .= '0 -12 Td ';
            }

            $text .= '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line).') Tj ';
        }

        $stream = $text.'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".count($offsets)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".count($offsets)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }
}
