<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.twilio.sid', 'AC_test');
        Config::set('services.twilio.token', 'token');
        Config::set('services.twilio.from', '+447000000000');
        Config::set('services.twilio.enabled', true);
        Config::set('services.twilio.webhook_secret', 'sekret');
    }

    private function unverifiedUser(): User
    {
        return User::factory()->create(['phone' => '+447700900111', 'phone_verified_at' => null]);
    }

    public function test_send_code_sms_and_stores_a_hashed_code()
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SMx', 'status' => 'queued'])]);
        $user = $this->unverifiedUser();

        $this->actingAs($user)
            ->postJson('/api/users/me/phone/send-code')
            ->assertOk();

        $user->refresh();
        $this->assertNotNull($user->phone_verification_code);
        $this->assertNotNull($user->phone_verification_sent_at);
        // The plaintext code is never stored.
        $this->assertNotEquals(6, strlen($user->phone_verification_code));

        // It was sent as a phone_verification SMS (recorded for delivery tracking).
        $this->assertDatabaseHas('sms_messages', ['user_id' => $user->id, 'context' => 'phone_verification']);
    }

    public function test_send_code_requires_a_phone_number()
    {
        $user = User::factory()->create(['phone' => null, 'phone_verified_at' => null]);

        $this->actingAs($user)
            ->postJson('/api/users/me/phone/send-code')
            ->assertStatus(422);
    }

    public function test_verify_marks_the_number_verified_with_the_correct_code()
    {
        $user = $this->unverifiedUser();
        $user->forceFill([
            'phone_verification_code' => Hash::make('123456'),
            'phone_verification_sent_at' => now(),
            'phone_verification_attempts' => 0,
        ])->save();

        $this->actingAs($user)
            ->postJson('/api/users/me/phone/verify', ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.phone_verified', true);

        $user->refresh();
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNull($user->phone_verification_code);
    }

    public function test_verify_rejects_a_wrong_code_and_counts_the_attempt()
    {
        $user = $this->unverifiedUser();
        $user->forceFill([
            'phone_verification_code' => Hash::make('123456'),
            'phone_verification_sent_at' => now(),
        ])->save();

        $this->actingAs($user)
            ->postJson('/api/users/me/phone/verify', ['code' => '000000'])
            ->assertStatus(422);

        $user->refresh();
        $this->assertNull($user->phone_verified_at);
        $this->assertSame(1, $user->phone_verification_attempts);
    }

    public function test_verify_rejects_an_expired_code()
    {
        $user = $this->unverifiedUser();
        $user->forceFill([
            'phone_verification_code' => Hash::make('123456'),
            'phone_verification_sent_at' => now()->subMinutes(20),
        ])->save();

        $this->actingAs($user)
            ->postJson('/api/users/me/phone/verify', ['code' => '123456'])
            ->assertStatus(422);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_changing_phone_number_resets_verification()
    {
        $user = User::factory()->create(['phone' => '+447700900111', 'phone_verified_at' => now()]);

        $this->actingAs($user)
            ->postJson('/api/users/me', ['phone' => '+447700900222'])
            ->assertOk()
            ->assertJsonPath('data.phone_verified', false);

        $this->assertNull($user->fresh()->phone_verified_at);
    }
}
