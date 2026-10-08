<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('currency.index'))->assertRedirect(route('login'));
    }

    public function test_it_lists_the_latest_rate_per_currency(): void
    {
        $this->authenticateWithCompany();

        CurrencyRate::create(['currency_code' => 'USD', 'currency_name' => 'Dólar estadounidense', 'rate_to_cop' => 4000, 'rate_date' => now()->subDay()->toDateString()]);
        CurrencyRate::create(['currency_code' => 'USD', 'currency_name' => 'Dólar estadounidense', 'rate_to_cop' => 4100, 'rate_date' => now()->toDateString()]);

        $response = $this->get(route('currency.index'))->assertOk();
        $response->assertSee('4,100.00');
    }

    public function test_it_stores_a_new_rate_and_updates_an_existing_one_for_the_same_date(): void
    {
        $this->authenticateWithCompany();

        $this->post(route('currency.store'), [
            'currency_code' => 'usd',
            'rate_to_cop' => 4123.5,
            'rate_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('currency_rates', ['currency_code' => 'USD', 'rate_to_cop' => 4123.5000]);

        $this->post(route('currency.store'), [
            'currency_code' => 'usd',
            'rate_to_cop' => 4200,
            'rate_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseCount('currency_rates', 1);
        $this->assertDatabaseHas('currency_rates', ['currency_code' => 'USD', 'rate_to_cop' => 4200.0000]);
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
