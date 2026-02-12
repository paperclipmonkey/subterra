<?php

namespace Tests\Feature;

use App\Events\UserCreated;
use App\Listeners\SendNewUserSignupEmailToAdmins;
use App\Listeners\SendUserCreatedSlackAlert;
use App\Mail\NewUserSignupNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\TestCase;

class UserSignupNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_does_not_trigger_notifications()
    {
        Mail::fake();
        Event::fake([
            // We want to test the LISTENERS, so we shouldn't fake the event itself if we were firing it manually,
            // but here we want to manually fire the event and check the specific listeners.
            // Actually, better to just instantiate the listener and call handle.
        ]);

        // Create an inactive user (simulating trip participant addition)
        $user = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Inactive Participant',
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $event = new UserCreated($user);

        // Test Email Listener
        $emailListener = new SendNewUserSignupEmailToAdmins();
        $emailListener->handle($event);
        Mail::assertNothingSent();

        // Test Slack Listener
        // Mock the facade
        SlackAlert::shouldReceive('to')->never();

        $slackListener = new SendUserCreatedSlackAlert();
        $slackListener->handle($event);
    }

    public function test_active_user_triggers_notifications()
    {
        Mail::fake();

        // Create an admin to receive the email
        User::factory()->admin()->create(['email' => 'admin@subterra.world']);

        // Create an active user (simulating magic link signup)
        $user = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'is_active' => true,
        ]);

        $event = new UserCreated($user);

        // Test Email Listener
        $emailListener = new SendNewUserSignupEmailToAdmins();
        $emailListener->handle($event);

        Mail::assertQueued(NewUserSignupNotification::class);

        // Test Slack Listener is a bit harder to mock perfectly without potentially interfering with other tests if not careful,
        // but let's try a simple mock expectation.
        SlackAlert::shouldReceive('to')->with('signups')->once()->andReturnSelf();
        SlackAlert::shouldReceive('message')->once();

        $slackListener = new SendUserCreatedSlackAlert();
        $slackListener->handle($event);
    }

    public function test_admin_index_filters_inactive_users()
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $activeUser = User::factory()->create(['is_active' => true, 'name' => 'Active User']);
        $inactiveUser = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // ID check
        $ids = array_column($data, 'id');
        $this->assertContains($activeUser->id, $ids);
        $this->assertNotContains($inactiveUser->id, $ids);
    }

    public function test_delayed_user_activation_triggers_notification()
    {
        Mail::fake();
        // Create an inactive user (simulating trip participant addition)
        $user = User::withoutGlobalScopes()->forceCreate([
            'name' => 'Delayed User',
            'email' => 'delayed@example.com',
            'is_active' => false,
        ]);

        // Simulate activation login logic (manually specificing the transition we added to controllers)
        $user->is_active = true;
        $user->save();

        // Fire the event manually as the Controller would
        event(new UserCreated($user));

        // Create an admin to receive the email
        User::factory()->admin()->create(['email' => 'admin@subterra.world']);

        // Check listener handles it because is_active is now true
        $emailListener = new SendNewUserSignupEmailToAdmins();
        $emailListener->handle(new UserCreated($user));

        Mail::assertQueued(NewUserSignupNotification::class);
    }
}
