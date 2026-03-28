<?php

namespace Tests\Feature;

use App\Mail\MagicLinkMail;
use App\Mail\TripTaggedMail;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_magic_link_mail_renders_correctly()
    {
        $mail = new MagicLinkMail('http://localhost/magic/link');

        $rendered = $mail->render();

        $this->assertStringContainsString('subterra-logo.png', $rendered);
        $this->assertStringContainsString('Login to Your Account', $rendered);
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
}
