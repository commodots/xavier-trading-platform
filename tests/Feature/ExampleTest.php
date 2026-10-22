<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('name="description"', false)
            ->assertSee('name="robots" content="index,follow"', false)
            ->assertSee('Xavier', false);
    }

    public function test_client_application_routes_can_be_loaded_directly_without_a_web_session(): void
    {
        foreach (['/dashboard', '/wallet', '/profile'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('id="app"', false);
        }
    }
}
