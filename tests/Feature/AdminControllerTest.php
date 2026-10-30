<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Purchase;
use App\Models\User;
use App\Support\DefaultChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_admin_can_create_a_user_account_and_assign_a_company_role(): void
    {
        $company = $this->authenticateWithCompany();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'role' => 'contador',
        ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Acceso creado y vinculado a la empresa. Comparte la contraseña inicial de forma segura.');
        $user = User::where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertSame('Usuario nuevo', $user->name);
        $this->assertTrue(Hash::check('password-123', $user->password));
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'contador',
        ]);
    }

    public function test_admin_panel_displays_user_access_creation_and_company_members(): void
    {
        $company = $this->authenticateWithCompany();
        $member = User::factory()->create();
        $company->users()->attach($member->id, ['role' => 'vendedor']);

        $this->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Crear acceso')
            ->assertSee('Vincular una cuenta existente')
            ->assertSee($member->name)
            ->assertSee($member->email)
            ->assertSee('Guardar rol');
    }

    public function test_admin_can_change_a_member_role_and_the_saved_role_controls_module_access(): void
    {
        $company = $this->authenticateWithCompany();
        $member = User::factory()->create();
        $company->users()->attach($member->id, ['role' => 'consulta']);

        $response = $this->put(route('admin.members.role', $member), ['role' => 'vendedor']);

        $response->assertRedirect()->assertSessionHas('success', 'Rol actualizado.');
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => 'vendedor',
        ]);

        $this->actingAs($member)->withSession(['company_id' => $company->id]);
        $this->post(route('sales.store'), [])->assertSessionHasErrors();
        $this->post(route('purchases.store'), [])->assertForbidden();
    }

    public function test_admin_can_link_an_existing_account_to_the_active_company(): void
    {
        $company = $this->authenticateWithCompany();
        $member = User::factory()->create();

        $response = $this->post(route('admin.members.store'), [
            'email' => $member->email,
            'role' => 'auxiliar',
        ]);

        $response->assertRedirect()->assertSessionHas('success', 'Usuario agregado a la empresa.');
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $member->id,
            'role' => 'auxiliar',
        ]);
    }

    public function test_admin_cannot_remove_the_last_administrator_role(): void
    {
        $company = $this->authenticateWithCompany();
        $administrator = auth()->user();

        $this->followingRedirects()
            ->from(route('admin.index'))
            ->put(route('admin.members.role', $administrator), ['role' => 'consulta'])
            ->assertSee('La empresa debe conservar al menos un administrador.');

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $administrator->id,
            'role' => 'admin',
        ]);
    }

    public function test_admin_cannot_change_a_company_owner_role(): void
    {
        $company = $this->authenticateWithCompany();
        $owner = User::factory()->create();
        $company->users()->attach($owner->id, ['role' => 'owner']);

        $this->followingRedirects()
            ->from(route('admin.index'))
            ->put(route('admin.members.role', $owner), ['role' => 'consulta'])
            ->assertSee('El rol de propietario no se puede cambiar.');

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }

    public function test_new_company_is_listed_and_becomes_active_for_the_admin(): void
    {
        $this->authenticateWithCompany();

        $response = $this->post(route('admin.companies.store'), ['name' => 'Empresa visible']);

        $response->assertRedirect()->assertSessionHas('success');
        $company = Company::where('name', 'Empresa visible')->firstOrFail();
        $this->assertSame($company->id, (int) session('company_id'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Empresa visible');
    }

    public function test_admin_sees_validation_errors_when_a_new_user_password_does_not_match(): void
    {
        $this->authenticateWithCompany();

        $response = $this->followingRedirects()->from(route('admin.index'))->post(route('admin.users.store'), [
            'name' => 'Usuario nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'different-password',
            'role' => 'contador',
        ]);

        $response->assertOk()
            ->assertSee('No se pudo completar la acción');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_non_admin_cannot_create_user_accounts(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'contador']);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        $this->post(route('admin.users.store'), [
            'name' => 'Usuario no autorizado',
            'email' => 'no-autorizado@example.com',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'role' => 'admin',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'no-autorizado@example.com']);
    }

    public function test_admin_cannot_change_a_role_for_a_user_from_another_company(): void
    {
        $this->authenticateWithCompany();
        $otherCompany = Company::factory()->create();
        $otherCompanyMember = User::factory()->create();
        $otherCompany->users()->attach($otherCompanyMember->id, ['role' => 'admin']);

        $this->put(route('admin.members.role', $otherCompanyMember), ['role' => 'consulta'])
            ->assertNotFound();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $otherCompany->id,
            'user_id' => $otherCompanyMember->id,
            'role' => 'admin',
        ]);
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

    public function test_admin_can_store_factus_credentials_for_their_company(): void
    {
        $company = $this->authenticateWithCompany();

        $this->post(route('admin.factus.update'), [
            'environment' => 'sandbox',
            'client_id' => 'client-123',
            'client_secret' => 'secret-123',
            'username' => 'empresa@example.com',
            'password' => 'super-secret',
            'invoice_numbering_range_id' => 4,
        ])->assertRedirect();

        $credential = $company->factusCredential()->firstOrFail();
        $this->assertSame('client-123', $credential->client_id);
        $this->assertSame('secret-123', $credential->client_secret);
        $this->assertSame(4, $credential->invoice_numbering_range_id);
    }

    public function test_admin_can_configure_the_social_security_operator(): void
    {
        $company = $this->authenticateWithCompany();

        $this->post(route('admin.social-security.update'), [
            'social_security_operator_name' => 'Enlace Operativo',
            'social_security_operator_url' => 'https://www.enlaceoperativo.com',
        ])->assertRedirect();

        $company->refresh();
        $this->assertSame('Enlace Operativo', $company->social_security_operator_name);
        $this->assertSame('https://www.enlaceoperativo.com', $company->social_security_operator_url);
    }

    public function test_admin_can_configure_tax_rates(): void
    {
        $company = $this->authenticateWithCompany();

        $this->post(route('admin.tax-settings.update'), [
            'ica_rate_per_thousand' => 6.9,
            'income_tax_rate_percentage' => 35,
        ])->assertRedirect();

        $company->refresh();
        $this->assertSame(6.9, (float) $company->ica_rate_per_thousand);
        $this->assertSame(35.0, (float) $company->income_tax_rate_percentage);
    }

    public function test_admin_can_create_a_new_payment_method_with_an_account(): void
    {
        $company = $this->authenticateWithCompany();
        $account = ChartOfAccount::create([
            'company_id' => $company->id,
            'code' => '2205-TEST',
            'name' => 'Proveedores nacionales',
            'class' => 2,
            'nature' => 'credit',
            'allows_posting' => true,
            'active' => true,
        ]);

        $this->post(route('admin.payment-methods.store'), [
            'name' => 'Transferencia proveedor',
            'chart_of_account_id' => $account->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('payment_methods', [
            'company_id' => $company->id,
            'name' => 'Transferencia proveedor',
            'chart_of_account_id' => $account->id,
        ]);
    }

    public function test_admin_cannot_create_two_payment_methods_with_the_same_name(): void
    {
        $company = $this->authenticateWithCompany();
        PaymentMethod::create(['company_id' => $company->id, 'name' => 'Nequi', 'is_editable' => true]);

        $this->post(route('admin.payment-methods.store'), ['name' => 'Nequi'])
            ->assertSessionHasErrors('name');
    }

    public function test_admin_can_delete_an_unused_payment_method(): void
    {
        $company = $this->authenticateWithCompany();
        $method = PaymentMethod::create(['company_id' => $company->id, 'name' => 'Nequi', 'is_editable' => true]);

        $this->delete(route('admin.payment-methods.destroy', $method))->assertRedirect();

        $this->assertDatabaseMissing('payment_methods', ['id' => $method->id]);
    }

    public function test_admin_cannot_delete_a_payment_method_already_used_by_a_purchase(): void
    {
        $company = $this->authenticateWithCompany();
        $method = PaymentMethod::create(['company_id' => $company->id, 'name' => 'Nequi', 'is_editable' => true]);
        Purchase::create([
            'company_id' => $company->id,
            'invoice_number' => 'FC-1',
            'provider' => 'Proveedor',
            'payment_method_id' => $method->id,
            'purchase_date' => now()->toDateString(),
            'subtotal' => 100,
            'iva_total' => 0,
            'retefuente' => 0,
            'total_pagar' => 100,
        ]);

        $this->delete(route('admin.payment-methods.destroy', $method))->assertStatus(422);
        $this->assertDatabaseHas('payment_methods', ['id' => $method->id]);
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
