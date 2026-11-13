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

    public function test_third_party_saves_fiscal_responsibility_codes_from_the_rut(): void
    {
        $this->authenticateWithCompany();

        $this->post(route('third-parties.store'), [
            'type' => 'natural',
            'name' => 'Ana Pérez',
            'document' => '123456789',
            'is_customer' => '1',
            'fiscal_responsibilities' => '05, 48, 05, r-99-pn',
        ])->assertRedirect();

        $thirdParty = ThirdParty::query()->where('document', '123456789')->firstOrFail();
        $this->assertSame(['05', '48', 'R-99-PN'], $thirdParty->fiscal_responsibilities);
    }

    public function test_third_party_rejects_malformed_fiscal_responsibility_codes(): void
    {
        $this->authenticateWithCompany();

        $this->postJson(route('third-parties.store'), [
            'type' => 'natural',
            'name' => 'Ana Pérez',
            'document' => '123456789',
            'is_customer' => '1',
            'fiscal_responsibilities' => '05, <script>',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('fiscal_responsibilities');

        $this->assertDatabaseMissing('third_parties', ['document' => '123456789']);
    }

    public function test_guest_cannot_upload_a_rut_pdf(): void
    {
        $this->post(route('third-parties.rut.extract'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_search_or_view_third_parties(): void
    {
        $this->get(route('third-parties.search', ['q' => 'Acme']))
            ->assertRedirect(route('login'));
        $this->get(route('third-parties.show', 1))
            ->assertRedirect(route('login'));
    }

    public function test_template_can_be_downloaded(): void
    {
        $this->authenticateWithCompany();

        $response = $this->get(route('third-parties.template'));

        $response->assertOk();
        $response->assertSee('Es cliente (si/no)', false)
            ->assertSee('DV (si aplica)', false)
            ->assertSee('Responsabilidades fiscales del RUT', false);
    }

    public function test_index_displays_edit_and_rut_autocomplete_actions(): void
    {
        $company = $this->authenticateWithCompany();
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'type' => 'natural',
            'name' => 'Ana Pérez',
            'document' => '123456789',
            'verification_digit' => '0',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Bogotá',
            'neighborhood' => 'Centro',
            'is_customer' => true,
        ]);

        $response = $this->get(route('third-parties.index'));

        $response->assertSee('Editar')
            ->assertSee('Autocompletar desde PDF del RUT')
            ->assertSee('Responsabilidades fiscales (RUT, casilla 53)')
            ->assertSee('DV (si aplica)')
            ->assertSee('Ver ficha')
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
            'verification_digit' => '0',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Bogotá',
            'neighborhood' => 'Centro',
            'is_customer' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('third_parties', [
            'company_id' => $company->id,
            'name' => 'Ana Pérez',
            'created_by' => auth()->id(),
            'verification_digit' => '0',
            'address' => 'Calle 10 # 20-30',
            'city' => 'Bogotá',
            'neighborhood' => 'Centro',
        ]);
    }

    public function test_third_party_creation_calculates_the_nit_verification_digit(): void
    {
        $company = $this->authenticateWithCompany();

        $response = $this->post(route('third-parties.store'), [
            'type' => 'juridica',
            'name' => 'Empresa de prueba',
            'document' => '800197268',
            'verification_digit' => '9',
            'is_supplier' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('third_parties', [
            'company_id' => $company->id,
            'document' => '800197268',
            'verification_digit' => '4',
        ]);
    }

    public function test_third_party_creation_rejects_a_verification_digit_longer_than_one_character(): void
    {
        $this->authenticateWithCompany();

        $response = $this->postJson(route('third-parties.store'), [
            'type' => 'natural',
            'name' => 'Persona de prueba',
            'document' => '123456789',
            'verification_digit' => '12',
            'is_customer' => '1',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('verification_digit');
        $this->assertDatabaseMissing('third_parties', ['document' => '123456789']);
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
            'verification_digit' => '7',
            'email' => 'contacto@example.com',
            'phone' => '3001234567',
            'address' => 'Carrera 10 # 20-30',
            'city' => 'Medellín',
            'neighborhood' => 'Laureles',
            'fiscal_responsibilities' => '05; 48',
            'is_supplier' => '1',
            'active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('third_parties', [
            'id' => $thirdParty->id,
            'type' => 'juridica',
            'name' => 'Comercial Nuevo SAS',
            'document' => '900123456',
            'verification_digit' => '8',
            'email' => 'contacto@example.com',
            'address' => 'Carrera 10 # 20-30',
            'city' => 'Medellín',
            'neighborhood' => 'Laureles',
            'is_customer' => false,
            'is_supplier' => true,
            'active' => true,
        ]);
        $this->assertSame(['05', '48'], $thirdParty->fresh()->fiscal_responsibilities);
    }

    public function test_third_party_details_show_the_creator_and_allow_editing(): void
    {
        $company = $this->authenticateWithCompany();
        $creator = User::factory()->create(['name' => 'Creadora de prueba', 'email' => 'creadora@example.com']);
        $thirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'created_by' => $creator->id,
            'type' => 'juridica',
            'name' => 'Comercial Nuevo SAS',
            'document' => '900123456',
            'verification_digit' => '8',
            'email' => 'contacto@example.com',
            'phone' => '3001234567',
            'address' => 'Carrera 10 # 20-30',
            'city' => 'Medellín',
            'neighborhood' => 'Laureles',
            'is_supplier' => true,
            'active' => true,
            'fiscal_responsibilities' => ['05', '48'],
        ]);

        $this->get(route('third-parties.show', $thirdParty))
            ->assertOk()
            ->assertSee('Comercial Nuevo SAS')
            ->assertSee('900123456-8')
            ->assertSee('05, 48')
            ->assertSee('Creadora de prueba')
            ->assertSee('creadora@example.com')
            ->assertSee(route('third-parties.index', ['edit' => $thirdParty->id]));
    }

    public function test_third_party_search_returns_only_matches_from_the_active_company(): void
    {
        $company = $this->authenticateWithCompany();
        $user = auth()->user();
        $otherCompany = Company::factory()->create();
        $user->companies()->attach($otherCompany->id, ['role' => 'admin']);
        $localThirdParty = ThirdParty::create([
            'company_id' => $company->id,
            'type' => 'juridica',
            'name' => 'Acme Local',
            'document' => '800197268',
            'verification_digit' => '4',
            'is_supplier' => true,
        ]);
        ThirdParty::create([
            'company_id' => $otherCompany->id,
            'type' => 'juridica',
            'name' => 'Acme Ajena',
            'document' => '800197269',
            'is_supplier' => true,
        ]);

        $nameSearch = $this->getJson(route('third-parties.search', ['q' => 'Acme']));
        $nameSearch
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acme Local')
            ->assertJsonPath('data.0.verification_digit', '4')
            ->assertJsonPath('data.0.url', route('third-parties.show', $localThirdParty))
            ->assertJsonMissing(['name' => 'Acme Ajena']);

        $this->getJson(route('third-parties.search', ['q' => '800197268']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acme Local');
    }

    public function test_third_party_details_are_not_available_from_another_company(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $thirdParty = ThirdParty::create([
            'company_id' => $otherCompany->id,
            'type' => 'natural',
            'name' => 'Tercero privado',
            'is_customer' => true,
        ]);

        $this->get(route('third-parties.show', $thirdParty))
            ->assertNotFound();
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
            ->assertJsonPath('data.verification_digit', '8')
            ->assertJsonPath('data.type', 'juridica')
            ->assertJsonPath('data.address', 'Carrera 10 # 20-30')
            ->assertJsonPath('data.city', 'BOGOTA D.C.')
            ->assertJsonPath('data.email', 'contacto@example.com');
        $response->assertJsonPath('data.fiscal_responsibilities', '05, 07');
    }

    public function test_rut_pdf_assembles_a_natural_person_name(): void
    {
        $this->authenticateWithCompany();
        $file = UploadedFile::fake()->createWithContent('rut.pdf', $this->rutPdfContent([
            '5. Numero de Identificacion Tributaria (NIT)',
            '123456789',
            '6. DV',
            '8',
            '7. Primer apellido',
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
            ->assertJsonPath('data.verification_digit', '8')
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

        $csv = "Tipo;Nombre;Documento;Correo;Telefono;Cliente;Proveedor;Empleado;DV;Responsabilidades\r\n"
            ."natural;Juan Perez;123456789;juan@example.com;3001234567;si;no;no;;\"05, 48\"\r\n"
            ."juridica;Proveedor Actualizado;900111222;ventas@proveedor.com;6012345678;no;si;no;;07\r\n"
            .";;;;;;;;;\r\n";
        $file = UploadedFile::fake()->createWithContent('terceros.csv', $csv);

        $response = $this->post(route('third-parties.import'), ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseHas('third_parties', [
            'document' => '123456789',
            'name' => 'Juan Perez',
            'is_customer' => true,
            'is_supplier' => false,
            'created_by' => auth()->id(),
        ]);
        $this->assertDatabaseHas('third_parties', [
            'document' => '900111222',
            'name' => 'Proveedor Actualizado',
            'is_supplier' => true,
            'created_by' => null,
        ]);
        $this->assertDatabaseHas('third_parties', ['document' => '900111222', 'verification_digit' => '1']);
        $this->assertSame(['05', '48'], ThirdParty::query()->where('document', '123456789')->firstOrFail()->fiscal_responsibilities);
        $this->assertSame(2, ThirdParty::count());
    }

    public function test_import_rejects_files_with_more_than_500_records(): void
    {
        $this->authenticateWithCompany();
        $rows = ['Tipo;Nombre;Documento;Correo;Telefono;Cliente;Proveedor;Empleado;DV;Responsabilidades'];
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
            '8',
            '7. Primer apellido',
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
            '53. Codigo',
            '05 - Impto. renta y compl. regimen ordinario',
            '07 - Retencion en la fuente a titulo de renta',
            '54. Codigo',
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
