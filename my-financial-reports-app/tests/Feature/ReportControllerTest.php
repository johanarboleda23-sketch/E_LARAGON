<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('reports');
    }

    public function test_user_can_view_financial_reports()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports');

        $response->assertStatus(200);
        $response->assertViewIs('reports.index');
    }

    public function test_user_can_export_financial_report_to_pdf()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/export/pdf');

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_user_can_export_financial_report_to_excel()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/reports/export/excel');

        $response->assertStatus(200);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
    }

    public function test_user_cannot_access_reports_without_authentication()
    {
        $response = $this->get('/reports');

        $response->assertRedirect('/login');
    }
}
