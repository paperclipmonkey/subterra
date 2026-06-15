<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Guards against the inline-throttle shared-key gotcha.
 *
 * Laravel's inline `throttle:N,M` middleware keys every counter by the same
 * request signature (sha1(user id) for authed requests) and shares it across
 * ALL inline-throttled routes — so unrelated routes collide on one bucket. We
 * replaced those with named limiters in AppServiceProvider, which namespace the
 * cache key by limiter name. These tests lock that isolation in.
 */
class RateLimiterIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const PER_USER = [
        'user-create',
        'assistant-chat',
        'assistant-feedback',
        'assistant-logbook-import',
        'duty-officer-test-self',
        'duty-officer-test-broadcast',
    ];

    private const PER_IP = [
        'magic-link',
        'webhook-twilio-sms',
        'webhook-twilio-voice',
        'webhook-gcp-media',
    ];

    private function resolveLimit(string $name, Request $request)
    {
        $callback = RateLimiter::limiter($name);
        $this->assertNotNull($callback, "Rate limiter [{$name}] is not registered.");

        $limit = $callback($request);

        return is_array($limit) ? $limit[0] : $limit;
    }

    private function userRequest(User $user): Request
    {
        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * Mirror the cache key built by Illuminate\Routing\Middleware\ThrottleRequests
     * for a named limiter: md5(limiterName . limit->key).
     */
    private function effectiveKey(string $name, $limit): string
    {
        return md5($name.$limit->key);
    }

    public function test_every_throttled_route_has_its_own_isolated_bucket()
    {
        $user = User::factory()->create();

        $keys = [];
        foreach (self::PER_USER as $name) {
            $keys[$name] = $this->effectiveKey($name, $this->resolveLimit($name, $this->userRequest($user)));
        }
        foreach (self::PER_IP as $name) {
            $keys[$name] = $this->effectiveKey($name, $this->resolveLimit($name, Request::create('/api/test', 'POST')));
        }

        $this->assertSameSize(
            $keys,
            array_unique($keys),
            'Named limiters must not share a cache key — each route needs its own budget.'
        );
    }

    public function test_same_user_resolves_to_different_buckets_per_route()
    {
        // The regression: two routes for the SAME user previously shared one
        // counter. The raw ->by() identity is intentionally identical; isolation
        // must come from the limiter name.
        $user = User::factory()->create();
        $request = $this->userRequest($user);

        $a = $this->resolveLimit('user-create', $request);
        $b = $this->resolveLimit('assistant-chat', $request);

        $this->assertSame($a->key, $b->key, 'Per-user limiters key by the same user identity.');
        $this->assertNotSame(
            $this->effectiveKey('user-create', $a),
            $this->effectiveKey('assistant-chat', $b),
            'Same identity must still land in different buckets via the limiter name.'
        );
    }

    public function test_named_limiters_preserve_their_original_limits()
    {
        $user = User::factory()->create();
        $request = $this->userRequest($user);

        // [maxAttempts, decaySeconds] — matches the old throttle:N,M values.
        $expected = [
            'user-create' => [10, 60],
            'assistant-chat' => [50, 1440 * 60],
            'assistant-feedback' => [60, 60 * 60],
            'assistant-logbook-import' => [20, 1440 * 60],
            'duty-officer-test-self' => [10, 60],
            'duty-officer-test-broadcast' => [3, 5 * 60],
        ];

        foreach ($expected as $name => [$maxAttempts, $decaySeconds]) {
            $limit = $this->resolveLimit($name, $request);
            $this->assertSame($maxAttempts, $limit->maxAttempts, "[{$name}] maxAttempts mismatch");
            $this->assertSame($decaySeconds, $limit->decaySeconds, "[{$name}] decaySeconds mismatch");
        }
    }

    public function test_user_create_throttle_enforces_its_own_limit_over_http()
    {
        // End-to-end proof the named limiter is wired: 10 allowed, 11th blocked.
        $user = User::factory()->create();

        for ($i = 0; $i < 10; ++$i) {
            $response = $this->actingAs($user)->postJson('/api/users', []);
            $this->assertNotSame(429, $response->status(), "Request {$i} should not be throttled yet.");
        }

        $this->actingAs($user)->postJson('/api/users', [])->assertStatus(429);
    }
}
