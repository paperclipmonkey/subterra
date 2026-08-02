<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    /** A tiny valid GIF so the avatar passes imagecreatefromstring() validation. */
    private const TINY_GIF_BASE64 = 'R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';

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

    public function test_established_user_logs_in_without_profile_being_touched()
    {
        config(['app.url' => 'http://localhost']);
        Http::fake();
        Event::fake([UserCreated::class]);

        $user = User::factory()->create([
            'name' => 'Custom Name',
            'photo' => 'avatars/custom-photo.webp',
        ]);

        $this->mockGoogleUser($user->email, 'Google Name');

        $response = $this->get('/api/google/callback?code=fake-code');

        $response->assertRedirect('http://localhost');
        $this->assertAuthenticatedAs($user);

        // The signup branch must not run: no avatar download, no overwrite of
        // the user's custom name or photo.
        Http::assertNothingSent();
        $fresh = $user->fresh();
        $this->assertEquals('Custom Name', $fresh->name);
        $this->assertEquals('avatars/custom-photo.webp', $fresh->photo);
        Event::assertNotDispatched(UserCreated::class);
    }

    public function test_new_user_is_created_with_google_profile()
    {
        config(['app.url' => 'http://localhost']);
        Storage::fake('media');
        Http::fake([
            '*' => Http::response(base64_decode(self::TINY_GIF_BASE64), 200, ['Content-Type' => 'image/gif']),
        ]);
        Event::fake([UserCreated::class]);

        $this->mockGoogleUser('new-caver@example.com', 'New Caver');

        $response = $this->get('/api/google/callback?code=fake-code');

        $response->assertRedirect('http://localhost');

        $user = User::where('email', 'new-caver@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('New Caver', $user->name);
        $this->assertNotNull($user->photo);
        $this->assertNotNull($user->tos_agreed_at);
        $this->assertTrue($user->is_active);
        Event::assertDispatched(UserCreated::class);
    }

    public function test_placeholder_user_gets_missing_profile_filled_and_is_reactivated()
    {
        config(['app.url' => 'http://localhost']);
        Storage::fake('media');
        Http::fake([
            '*' => Http::response(base64_decode(self::TINY_GIF_BASE64), 200, ['Content-Type' => 'image/gif']),
        ]);
        Event::fake([UserCreated::class]);

        // Placeholder account, e.g. created when tagged in a trip.
        $user = User::factory()->create([
            'name' => null,
            'photo' => null,
            'is_active' => false,
            'tos_agreed_at' => null,
        ]);

        $this->mockGoogleUser($user->email, 'Google Name');

        $response = $this->get('/api/google/callback?code=fake-code');

        $response->assertRedirect('http://localhost');

        $fresh = $user->fresh();
        $this->assertEquals('Google Name', $fresh->name);
        $this->assertNotNull($fresh->photo);
        $this->assertTrue($fresh->is_active);
        // Reactivation is the implicit "sign up" action, so ToS agreement is
        // recorded just like first-time creation (mirrors the magic-link path).
        $this->assertNotNull($fresh->tos_agreed_at);
        Event::assertDispatched(UserCreated::class);
    }

    public function test_reactivation_never_overwrites_existing_name_or_photo()
    {
        config(['app.url' => 'http://localhost']);
        Http::fake();
        Event::fake([UserCreated::class]);

        $user = User::factory()->create([
            'name' => 'Existing Name',
            'photo' => 'avatars/existing.webp',
            'is_active' => false,
        ]);

        $this->mockGoogleUser($user->email, 'Different Google Name');

        $this->get('/api/google/callback?code=fake-code')->assertRedirect('http://localhost');

        // No avatar download when a photo already exists, and nothing clobbered.
        Http::assertNothingSent();
        $fresh = $user->fresh();
        $this->assertEquals('Existing Name', $fresh->name);
        $this->assertEquals('avatars/existing.webp', $fresh->photo);
        $this->assertTrue($fresh->is_active);
        Event::assertDispatched(UserCreated::class);
    }

    public function test_failed_avatar_fetch_does_not_block_signup_or_null_the_photo()
    {
        config(['app.url' => 'http://localhost']);
        Http::fake(['*' => Http::response('Server error', 500)]);
        Event::fake([UserCreated::class]);

        $user = User::factory()->create([
            'name' => 'Tagged Caver',
            'photo' => null,
            'is_active' => false,
        ]);

        $this->mockGoogleUser($user->email, 'Google Name');

        $this->get('/api/google/callback?code=fake-code')->assertRedirect('http://localhost');

        $fresh = $user->fresh();
        // Name kept, photo simply stays null, user is still reactivated.
        $this->assertEquals('Tagged Caver', $fresh->name);
        $this->assertNull($fresh->photo);
        $this->assertTrue($fresh->is_active);
    }

    /**
     * Stub the Socialite Google driver to return the given profile.
     */
    private function mockGoogleUser(string $email, string $name, ?string $avatar = 'https://lh3.googleusercontent.com/avatar.jpg'): void
    {
        $socialiteUser = (new \Laravel\Socialite\Two\User())->map([
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
        ]);

        Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
        Socialite::shouldReceive('stateless')->andReturnSelf();
        Socialite::shouldReceive('user')->andReturn($socialiteUser);
    }
}
