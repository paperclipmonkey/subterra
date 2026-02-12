<?php

namespace Tests\Feature\Admin;

use App\Mail\PlatformNews;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_send_test_email()
    {
        Mail::fake();

        $admin = User::factory()->admin()->create(['email_platform_news' => true]);
        
        $response = $this->actingAs($admin)
            ->postJson('/api/admin/communications/send', [
                'subject' => 'Test Subject',
                'body' => 'Test Body',
                'test_mode' => true,
            ]);

        $response->assertStatus(200);

        Mail::assertQueued(PlatformNews::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_admin_can_send_mass_email_to_subscribed_users()
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        
        $subscribedUsers = User::factory()->count(3)->create(['email_platform_news' => true]);
        $unsubscribedUsers = User::factory()->count(2)->create(['email_platform_news' => false]);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/communications/send', [
                'subject' => 'Mass Subject',
                'body' => 'Mass Body',
                'test_mode' => false,
            ]);

        $response->assertStatus(200);

        foreach ($subscribedUsers as $user) {
            Mail::assertQueued(PlatformNews::class, function ($mail) use ($user) {
                return $mail->hasTo($user->email);
            });
        }

        foreach ($unsubscribedUsers as $user) {
            Mail::assertNotQueued(PlatformNews::class, function ($mail) use ($user) {
                return $mail->hasTo($user->email);
            });
        }
    }

    public function test_unsubscribe_link_works()
    {
        $user = User::factory()->create(['email_platform_news' => true]);

        $url = URL::signedRoute('newsletter.unsubscribe', ['user' => $user->id]);

        $response = $this->get($url);

        $response->assertStatus(200);
        $this->assertFalse($user->fresh()->email_platform_news);
    }

    public function test_unsubscribe_link_fails_with_invalid_signature()
    {
        $user = User::factory()->create(['email_platform_news' => true]);

        $url = URL::signedRoute('newsletter.unsubscribe', ['user' => $user->id]);
        
        // Tamper with signature
        $url .= 'invalid';

        $response = $this->get($url);

        $response->assertStatus(403);
        $this->assertTrue($user->fresh()->email_platform_news);
    }

    public function test_placeholders_are_replaced_correctly()
    {
        Mail::fake();

        $user = User::factory()->admin()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'email_platform_news' => true
        ]);
        
        $response = $this->actingAs($user)
            ->postJson('/api/admin/communications/send', [
                'subject' => 'Hello {{ firstname }}',
                'body' => 'Your full name is {{ fullname }}. ID: {{ id }}',
                'test_mode' => true,
            ]);

        $response->assertStatus(200);

        Mail::assertQueued(PlatformNews::class, function ($mail) use ($user) {
            // Since replacement happens in constructor, properties should be updated immediately
            return $mail->subjectLine === 'Hello Test' &&
                   str_contains($mail->body, "Your full name is Test User") &&
                   str_contains($mail->body, "ID: {$user->id}");
        });
    }
}
