<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Mail\TripStartedDONotification;
use App\Mail\TripTaggedMail;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_mail_renders_correctly()
    {
        $mail = new MagicLinkMail('http://localhost/magic/link');

        $rendered = $mail->render();

        $this->assertStringContainsString('subterra-logo.png', $rendered);
        $this->assertStringContainsString('Log in to Your Account', $rendered);
    }

    public function test_trip_tagged_mail_renders_correctly()
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($creator);

        $mail = new TripTaggedMail($trip, $user, $creator);

        $rendered = $mail->render();

        $this->assertStringContainsString('subterra-logo.png', $rendered);
        $this->assertStringContainsString('Trip Tag Notification', $rendered);
    }

    #[Test]
    public function trip_tagged_mail_treats_an_active_member_without_tos_timestamp_as_existing(): void
    {
        // Magic-link sign-ups can leave tos_agreed_at null; they are still members.
        $user = User::factory()->create(['tos_agreed_at' => null]);
        $creator = User::factory()->create();
        $trip = Trip::factory()->create();

        $rendered = (new TripTaggedMail($trip, $user, $creator))->render();

        $this->assertStringNotContainsString('Welcome to Subterra', $rendered);
        $this->assertStringContainsString('You have been tagged in a trip on', $rendered);
    }

    #[Test]
    public function trip_tagged_mail_welcomes_a_placeholder_user(): void
    {
        $user = User::factory()->create();
        $user->is_active = false;
        $user->save();
        $creator = User::factory()->create();
        $trip = Trip::factory()->create();

        $rendered = (new TripTaggedMail($trip, $user, $creator))->render();

        $this->assertStringContainsString('Welcome to Subterra', $rendered);
    }

    #[Test]
    public function trip_tagged_mail_links_to_the_trip_short_id(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $trip = Trip::factory()->create();

        $rendered = (new TripTaggedMail($trip, $user, $creator))->render();

        $this->assertStringContainsString(url('/trips/'.$trip->short_id), $rendered);
        $this->assertStringNotContainsString(url('/trips/'.$trip->id).'"', $rendered);
    }

    #[Test]
    public function trip_tagged_mail_strips_html_from_the_description(): void
    {
        $user = User::factory()->create();
        $creator = User::factory()->create();
        $trip = Trip::factory()->create(['description' => 'BEC group trip<br />We arrived at the car park']);

        $rendered = (new TripTaggedMail($trip, $user, $creator))->render();

        $this->assertStringNotContainsString('&lt;br', $rendered);
        $this->assertStringContainsString('BEC group trip We arrived at the car park', $rendered);
    }

    #[Test]
    public function trip_started_do_notification_links_to_the_trip_short_id(): void
    {
        $creator = User::factory()->create();
        $trip = Trip::factory()->create();

        $rendered = (new TripStartedDONotification($trip, $creator))->render();

        $this->assertStringContainsString(url('/trips/'.$trip->short_id), $rendered);
        $this->assertStringNotContainsString(url('/trips/'.$trip->id).'"', $rendered);
    }
}
