<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MagicLinkThrottleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function one_address_cannot_be_flooded_with_sign_in_emails_from_many_ips(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 10; ++$i) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$i}"])
                ->postJson('/api/auth/magic-link', ['email' => 'victim@example.com'])
                ->assertStatus(200);
        }

        // A fresh IP, and different casing, still hits the per-address limit.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson('/api/auth/magic-link', ['email' => ' Victim@Example.com'])
            ->assertStatus(429);

        // Other addresses are unaffected.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
            ->postJson('/api/auth/magic-link', ['email' => 'someone-else@example.com'])
            ->assertStatus(200);
    }
}
