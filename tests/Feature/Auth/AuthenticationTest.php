<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_user_registration_creates_an_admin_and_their_company(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Ana Pérez',
            'company_name' => 'Empresa de Ana',
            'email' => 'ana@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
        $company = Company::query()->where('name', 'Empresa de Ana')->firstOrFail();

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($company->is_partner);
        $this->assertTrue($user->companies()->whereKey($company->id)->wherePivot('role', 'admin')->exists());
        $this->assertSame($company->id, (int) session('company_id'));
    }

    public function test_partner_registration_creates_a_partner_company_and_admin(): void
    {
        $response = $this->post(route('register.partner.store'), [
            'name' => 'Socio SU+',
            'company_name' => 'Empresa socia',
            'email' => 'socio@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $user = User::query()->where('email', 'socio@example.com')->firstOrFail();
        $company = Company::query()->where('name', 'Empresa socia')->firstOrFail();

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($company->is_partner);
        $this->assertTrue($user->companies()->whereKey($company->id)->wherePivot('role', 'admin')->exists());
        $this->assertSame($company->id, (int) session('company_id'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertSame($company->id, (int) session('company_id'));
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertSee('Administración');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
