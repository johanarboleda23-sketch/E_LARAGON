<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingResolution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_contador_can_register_a_numbering_resolution(): void
    {
        $company = $this->authenticateWithCompany('contador');

        $response = $this->post(route('numbering-resolutions.store'), [
            'document_type' => 'sale',
            'prefix' => 'FV',
            'resolution_number' => '18764012345',
            'resolution_date' => now()->toDateString(),
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addYear()->toDateString(),
            'range_from' => 1,
            'range_to' => 1000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('numbering_resolutions', [
            'company_id' => $company->id,
            'document_type' => 'sale',
            'resolution_number' => '18764012345',
            'next_number' => 1,
            'active' => true,
        ]);
    }

    public function test_vendedor_cannot_register_a_numbering_resolution(): void
    {
        $this->authenticateWithCompany('vendedor');

        $this->post(route('numbering-resolutions.store'), [
            'document_type' => 'sale',
            'resolution_number' => '18764012345',
            'range_from' => 1,
            'range_to' => 1000,
        ])->assertForbidden();
    }

    public function test_allocate_next_consumes_the_range_sequentially(): void
    {
        $company = $this->authenticateWithCompany('contador');

        NumberingResolution::factory()->create([
            'company_id' => $company->id,
            'document_type' => 'sale',
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 2,
            'next_number' => 1,
        ]);

        $this->assertSame('FV-1', NumberingResolution::allocateNext('sale'));
        $this->assertSame('FV-2', NumberingResolution::allocateNext('sale'));

        $this->expectException(\RuntimeException::class);
        NumberingResolution::allocateNext('sale');
    }

    public function test_allocate_next_rejects_an_expired_resolution(): void
    {
        $company = $this->authenticateWithCompany('contador');

        NumberingResolution::factory()->create([
            'company_id' => $company->id,
            'document_type' => 'sale',
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->expectException(\RuntimeException::class);
        NumberingResolution::allocateNext('sale');
    }

    public function test_allocate_next_returns_null_when_no_active_resolution_exists(): void
    {
        $this->authenticateWithCompany('contador');

        $this->assertNull(NumberingResolution::allocateNext('sale'));
    }

    public function test_contador_can_edit_a_numbering_resolution(): void
    {
        $company = $this->authenticateWithCompany('contador');

        $resolution = NumberingResolution::factory()->create([
            'company_id' => $company->id,
            'document_type' => 'sale',
            'prefix' => 'FV',
            'range_from' => 1,
            'range_to' => 100,
            'next_number' => 5,
        ]);

        $response = $this->put(route('numbering-resolutions.update', $resolution), [
            'active' => true,
            'prefix' => 'FE',
            'resolution_number' => $resolution->resolution_number,
            'range_from' => 1,
            'range_to' => 500,
            'next_number' => 20,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('numbering_resolutions', [
            'id' => $resolution->id,
            'prefix' => 'FE',
            'range_to' => 500,
            'next_number' => 20,
        ]);
    }

    private function authenticateWithCompany(string $role): Company
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id, ['role' => $role]);
        $this->actingAs($user)->withSession(['company_id' => $company->id]);

        return $company;
    }
}
