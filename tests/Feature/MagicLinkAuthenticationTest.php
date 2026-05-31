<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MagicLinkAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_send_magic_link_for_existing_user()
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Magic link sent! Check your email.',
                     'success' => true,
                 ]);

        Mail::assertSent(\App\Mail\MagicLinkMail::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_set_tos_agreed_at_if_checkbox_not_checked()
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'newuser_no_tos@example.com',
            // 'agreed_to_tos' => false // default or explicit false
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser_no_tos@example.com',
            'tos_agreed_at' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_send_magic_link_for_new_user()
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'newuser@example.com',
            'agreed_to_tos' => true,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Magic link sent! Check your email.',
                     'success' => true,
                 ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => null,
            'is_active' => true, // Changed to true as new users are now active
            'is_active' => true, // Changed to true as new users are now active
        ]);

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user->tos_agreed_at);

        Mail::assertSent(\App\Mail\MagicLinkMail::class, function ($mail) {
            return $mail->hasTo('newuser@example.com');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_email_format()
    {
        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_email_field()
    {
        $response = $this->postJson('/api/auth/magic-link', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_handle_api_callback_with_valid_token()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        // Create a magic link for the user using LoginAction
        $magicLink = \MagicLink\MagicLink::create(
            new \MagicLink\Actions\LoginAction($user),
            3600 // 1 hour
        );

        $response = $this->getJson('/api/auth/magic-link-callback?token='.$magicLink->id.':'.$magicLink->token);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user' => [
                         'id',
                         'name',
                         'email',
                     ],
                     'needs_profile',
                 ]);

        // Verify the user is now authenticated
        $this->assertAuthenticatedAs($user);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_invalid_token_for_api_callback()
    {
        $response = $this->getJson('/api/auth/magic-link-callback?token=invalid-token');

        $response->assertStatus(401)
                 ->assertJson([
                     'error' => 'Invalid or expired magic link',
                 ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_update_user_name_in_profile()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => null,
        ]);

        $this->actingAs($user);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'bio' => 'Updated bio',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_handle_multiple_magic_link_requests_for_same_user()
    {
        Mail::fake();

        // Use a unique email to avoid conflicts with other tests
        $email = 'unique-'.time().'@example.com';

        // Send first magic link
        $response1 = $this->postJson('/api/auth/magic-link', [
            'email' => $email,
        ]);

        $response1->assertStatus(200);

        // Verify user was created
        $user = \App\Models\User::where('email', $email)->first();
        $this->assertNotNull($user);

        // Verify that one email was sent
        Mail::assertSent(\App\Mail\MagicLinkMail::class, 1);

        // Send second magic link for the same user
        $response2 = $this->postJson('/api/auth/magic-link', [
            'email' => $email,
        ]);

        $response2->assertStatus(200)
                 ->assertJson([
                     'message' => 'Magic link sent! Check your email.',
                     'success' => true,
                 ]);

        // Verify that 2 emails were sent in total
        Mail::assertSent(\App\Mail\MagicLinkMail::class, 2);

        // Note: In v2.25.1+, old magic links are not automatically deleted.
        // They expire based on their configured lifetime.
        // This is acceptable because:
        // 1. Each link has a short lifetime
        // 2. Links are single-use (max_visits = 1)
        // 3. The signed serialization format prevents tampering
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_send_magic_link_for_inactive_user()
    {
        Mail::fake();

        // Create an inactive user
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'name' => 'Inactive User',
            'is_active' => false,
        ]);

        // Verify user is inactive before the request
        $this->assertDatabaseHas('users', [
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'inactive@example.com',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Magic link sent! Check your email.',
                     'success' => true,
                 ]);

        // Verify user is reactivated after the request
        $this->assertDatabaseHas('users', [
            'email' => 'inactive@example.com',
            'is_active' => true,
        ]);

        Mail::assertSent(\App\Mail\MagicLinkMail::class, function ($mail) {
            return $mail->hasTo('inactive@example.com');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_create_duplicate_user_for_inactive_user()
    {
        Mail::fake();

        // Create an inactive user
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'name' => 'Inactive User',
            'is_active' => false,
        ]);

        // Get the user count before the request
        $userCountBefore = User::withoutGlobalScopes()->where('email', 'inactive@example.com')->count();
        $this->assertEquals(1, $userCountBefore);

        $response = $this->postJson('/api/auth/magic-link', [
            'email' => 'inactive@example.com',
        ]);

        $response->assertStatus(200);

        // Verify no duplicate user was created
        $userCountAfter = User::withoutGlobalScopes()->where('email', 'inactive@example.com')->count();
        $this->assertEquals(1, $userCountAfter);

        // Verify the existing user was reactivated
        $this->assertDatabaseHas('users', [
            'email' => 'inactive@example.com',
            'is_active' => true,
        ]);
    }
}
