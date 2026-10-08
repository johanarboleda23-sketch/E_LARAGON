<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Purchase;
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
