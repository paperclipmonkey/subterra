<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Cave;
use App\Models\Permit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmbedCalendarTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function embed_calendar_is_public_and_returns_counts_without_authentication()
    {
        $permit = Permit::factory()->withMaxGroups(2)->create();
        $cave = Cave::factory()->create();
        $permit->caves()->attach($cave->id);

        $date = now()->format('Y-m-d');
        Booking::factory()->approved()->create(['permit_id' => $permit->id, 'date' => $date]);
        Booking::factory()->create(['permit_id' => $permit->id, 'date' => $date]); // pending

        // No actingAs() — the embed endpoint must work for anonymous visitors.
        $response = $this->getJson("/api/embed/permits/{$permit->slug}/calendar?month=".now()->format('Y-m'));

        $response->assertStatus(200)
            ->assertJsonPath("data.{$date}.booking_count", 1)
            ->assertJsonPath("data.{$date}.pending_count", 1)
            ->assertJsonPath("data.{$date}.available", true)
            ->assertJsonPath('permit.name', $permit->name)
            ->assertJsonPath('permit.slug', $permit->slug)
            ->assertJsonPath('permit.caves.0.name', $cave->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function embed_calendar_does_not_leak_personal_data()
    {
        $permit = Permit::factory()->create();
        $date = now()->format('Y-m-d');
        $booking = Booking::factory()->approved()->create([
            'permit_id' => $permit->id,
            'date' => $date,
            'notes' => 'Secret notes from the applicant',
        ]);

        $response = $this->getJson("/api/embed/permits/{$permit->slug}/calendar?month=".now()->format('Y-m'));

        $response->assertStatus(200);
        // The response is counts only — no applicant identity, emails or notes.
        $response->assertJsonMissing(['notes' => 'Secret notes from the applicant']);
        $this->assertStringNotContainsString($booking->applicant->email, $response->getContent());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function embed_calendar_returns_404_for_inactive_permit()
    {
        $permit = Permit::factory()->inactive()->create();

        $response = $this->getJson("/api/embed/permits/{$permit->slug}/calendar?month=".now()->format('Y-m'));

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function embed_calendar_validates_the_month_parameter()
    {
        $permit = Permit::factory()->create();

        $this->getJson("/api/embed/permits/{$permit->slug}/calendar")
            ->assertStatus(422);

        $this->getJson("/api/embed/permits/{$permit->slug}/calendar?month=not-a-month")
            ->assertStatus(422);
    }
}
