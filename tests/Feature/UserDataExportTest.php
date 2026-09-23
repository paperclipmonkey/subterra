<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Callout;
use App\Models\CalloutParticipant;
use App\Models\Collection;
use App\Models\PipFeedback;
use App\Models\Report;
use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Download my data" must include everything held about the user (UK GDPR access
 * and portability), without secrets or other people's contact details.
 */
class UserDataExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_export_covers_all_personal_data_held_about_the_user(): void
    {
        config(['audit.console' => true]);
        $user = User::factory()->create([
            'phone' => '+447700900123',
            'date_of_birth' => '1990-05-01',
            'bio' => 'Loves sumps',
        ]);
        $user->forceFill(['phone_verification_code' => 'secret-hash'])->save();
        $user->update(['name' => 'Renamed Caver']);

        $trip = Trip::factory()->create(['name' => 'Big Pull-through', 'description' => 'Wet']);
        $trip->participants()->attach($user);
        TripMedia::create(['trip_id' => $trip->id, 'filename' => 'trips/p_desktop.webp', 'title' => 'Pitch head']);

        $callout = Callout::factory()->create(['user_id' => $user->id, 'trip_plan' => 'Down and back', 'car_parking' => 'Layby']);
        CalloutParticipant::create(['callout_id' => $callout->id, 'name' => 'Friend', 'phone' => '+447700900999', 'email' => 'friend@example.com']);

        $othersCallout = Callout::factory()->create();
        CalloutParticipant::create(['callout_id' => $othersCallout->id, 'user_id' => $user->id, 'name' => 'Me', 'phone' => '+447700900123']);

        Booking::factory()->create(['user_id' => $user->id, 'notes' => 'Two cars']);
        Collection::factory()->create(['user_id' => $user->id, 'name' => 'My ticks']);
        Report::factory()->create(['reporter_id' => $user->id, 'details' => 'Wrong grade']);
        PipFeedback::create(['user_id' => $user->id, 'rating' => 1, 'comment' => 'Nice', 'transcript' => [['role' => 'user', 'content' => 'hi']]]);

        $response = $this->actingAs($user)->getJson('/api/user/export')->assertOk();
        $data = $response->json();
        $raw = $response->getContent();

        $this->assertSame('+447700900123', $data['profile']['phone']);
        $this->assertStringStartsWith('1990-05-01', (string) $data['profile']['date_of_birth']);
        $this->assertSame('Loves sumps', $data['profile']['bio']);
        $this->assertSame('Big Pull-through', $data['trips'][0]['name']);
        $this->assertSame('Pitch head', $data['trips'][0]['photos'][0]['title']);
        $this->assertSame('Down and back', $data['callouts'][0]['trip_plan']);
        $this->assertSame(['Friend'], $data['callouts'][0]['participants']);
        $this->assertSame($othersCallout->id, $data['callouts_listed_on'][0]['callout_id']);
        $this->assertSame('Two cars', $data['bookings'][0]['notes']);
        $this->assertSame('My ticks', $data['collections'][0]['name']);
        $this->assertSame('Wrong grade', $data['reports_filed'][0]['details']);
        $this->assertSame('Nice', $data['pip_feedback'][0]['comment']);
        $this->assertNotEmpty($data['account_history']);

        // No secrets, and no other people's contact details.
        $this->assertStringNotContainsString('secret-hash', $raw);
        $this->assertStringNotContainsString('+447700900999', $raw);
        $this->assertStringNotContainsString('friend@example.com', $raw);
    }
}
