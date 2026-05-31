<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_without_code_redirects_to_login()
    {
        // Set config app url for consistent testing
        config(['app.url' => 'http://localhost']);

        $response = $this->get('/api/google/callback');

        $response->assertRedirect('http://localhost/login');
    }

    public function test_google_callback_with_error_redirects_to_login()
    {
        config(['app.url' => 'http://localhost']);

        $response = $this->get('/api/google/callback?error=access_denied');

        $response->assertRedirect('http://localhost/login');
    }
}
