<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Laravel skips CSRF verification while running unit tests, so these tests swap in
 * a middleware that doesn't, to prove the SPA's session-authenticated API calls are
 * actually protected in production.
 */
class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'http://localhost:3000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class ($app, $app['encrypter']) extends ValidateCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    #[Test]
    public function a_stateful_api_write_without_a_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('Referer', self::FRONTEND.'/')
            ->postJson('/api/logout')
            ->assertStatus(419);
    }

    #[Test]
    public function a_stateful_api_write_with_the_session_token_is_accepted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['_token' => 'known-token'])
            ->withHeader('Referer', self::FRONTEND.'/')
            ->withHeader('X-CSRF-TOKEN', 'known-token')
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out']);
    }

    #[Test]
    public function webhooks_are_exempt_and_rely_on_their_own_secret(): void
    {
        config(['services.twilio.webhook_secret' => 'right-secret']);

        // Reaches the controller (and its secret check) rather than failing CSRF.
        $this->withHeader('Referer', self::FRONTEND.'/')
            ->post('/api/webhooks/twilio/wrong-secret/sms', ['From' => '+447111111111', 'Body' => 'OUT SAFE'])
            ->assertStatus(403);
    }
}
