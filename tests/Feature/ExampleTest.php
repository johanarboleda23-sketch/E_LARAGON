<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_redirects_root_to_the_dashboard(): void
    {
        $this->get('/')->assertRedirectToRoute('dashboard');

        $this->get(route('dashboard'))->assertOk();
    }
}
