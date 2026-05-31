<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\BookingApprovedMail;
use App\Mail\BookingRejectedMail;
use App\Mail\BookingSubmittedMail;
use App\Models\Booking;
use App\Models\Cave;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PermitTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accessOfficer;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->admin()->create();
        $this->accessOfficer = User::factory()->accessOfficer()->create();
        $this->regularUser = User::factory()->create();
    }

    // --- Admin Permit CRUD ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_list_permits()
    {
        Permit::factory()->count(3)->create();

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->getJson('/api/admin/permits');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regular_user_cannot_list_admin_permits()
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/permits');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_create_permit()
    {
        $cave = Cave::factory()->create();

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson('/api/admin/permits', [
                'name' => 'Symonds Yat Caves',
                'description' => 'Covers all caves around Symonds Yat.',
                'conditions' => 'Must be an experienced caver.',
                'has_max_groups_per_day' => true,
                'max_groups_per_day' => 2,
                'auto_approve' => false,
                'booking_info' => 'Collect key from the warden.',
                'cave_ids' => [$cave->id],
                'officer_ids' => [$this->accessOfficer->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Symonds Yat Caves']);

        $this->assertDatabaseHas('permits', ['name' => 'Symonds Yat Caves']);
        $this->assertDatabaseHas('cave_permit', ['cave_id' => $cave->id]);
        $this->assertDatabaseHas('permit_user', ['user_id' => $this->accessOfficer->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function create_permit_validates_required_fields()
    {
        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson('/api/admin/permits', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_update_permit_they_administer()
    {
        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/permits/{$permit->slug}", [
                'name' => 'Updated Permit Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Updated Permit Name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_admin_can_update_any_permit()
    {
        $permit = Permit::factory()->create();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/permits/{$permit->slug}", [
                'name' => 'Admin Updated',
            ]);

        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_delete_permit_they_administer()
    {
        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->deleteJson("/api/admin/permits/{$permit->slug}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('permits', ['id' => $permit->id]);
    }

    // --- Public Permit & Booking Endpoints ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_get_permit_for_cave()
    {
        $permit = Permit::factory()->create();
        $cave = Cave::factory()->create();
        $permit->caves()->attach($cave->id);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson("/api/caves/{$cave->slug}/permit");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $permit->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_null_for_cave_without_permit()
    {
        $cave = Cave::factory()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson("/api/caves/{$cave->slug}/permit");

        $response->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_get_calendar_for_permit()
    {
        $permit = Permit::factory()->withMaxGroups(2)->create();
        Booking::factory()->approved()->create([
            'permit_id' => $permit->id,
            'date' => now()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson("/api/permits/{$permit->slug}/calendar?month=".now()->format('Y-m'));

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'permit']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_submit_booking_application()
    {
        Mail::fake();

        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => now()->addDays(7)->format('Y-m-d'),
                'participants' => 4,
                'notes' => 'Looking forward to visiting.',
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('bookings', [
            'permit_id' => $permit->id,
            'user_id' => $this->regularUser->id,
            'status' => 'pending',
        ]);

        Mail::assertQueued(BookingSubmittedMail::class, function ($mail) {
            return $mail->hasTo($this->accessOfficer->email);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function auto_approve_permit_approves_immediately()
    {
        Mail::fake();

        $permit = Permit::factory()->autoApprove()->create();
        $permit->officers()->attach($this->accessOfficer->id);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => now()->addDays(7)->format('Y-m-d'),
                'participants' => 2,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'approved');

        Mail::assertQueued(BookingApprovedMail::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_book_inactive_permit()
    {
        $permit = Permit::factory()->inactive()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => now()->addDays(7)->format('Y-m-d'),
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_book_when_date_is_full()
    {
        $permit = Permit::factory()->withMaxGroups(1)->create();
        $date = now()->addDays(7)->format('Y-m-d');

        Booking::factory()->approved()->create([
            'permit_id' => $permit->id,
            'date' => $date,
        ]);

        // Verify the booking exists in DB
        $this->assertDatabaseHas('bookings', [
            'permit_id' => $permit->id,
            'status' => 'approved',
        ]);

        // Verify the permit has max groups enabled
        $freshPermit = Permit::find($permit->id);
        $this->assertTrue($freshPermit->has_max_groups_per_day);
        $this->assertEquals(1, $freshPermit->max_groups_per_day);
        $this->assertFalse($freshPermit->isDateAvailable($date));

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => $date,
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'This date is fully booked.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_book_past_date()
    {
        $permit = Permit::factory()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => now()->subDay()->format('Y-m-d'),
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(422);
    }

    // --- Admin Booking Management ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_list_all_bookings()
    {
        $permit = Permit::factory()->create();
        Booking::factory()->count(5)->create(['permit_id' => $permit->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->getJson('/api/admin/bookings');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_approve_booking()
    {
        Mail::fake();

        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);
        $booking = Booking::factory()->create(['permit_id' => $permit->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'approved',
        ]);

        Mail::assertQueued(BookingApprovedMail::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_reject_booking()
    {
        Mail::fake();

        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);
        $booking = Booking::factory()->create(['permit_id' => $permit->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/reject", [
                'rejection_reason' => 'Too many groups already.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'rejected');

        Mail::assertQueued(BookingRejectedMail::class);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function reject_requires_reason()
    {
        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);
        $booking = Booking::factory()->create(['permit_id' => $permit->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/reject", []);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_approve_already_approved_booking()
    {
        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);
        $booking = Booking::factory()->approved()->create(['permit_id' => $permit->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/approve");

        $response->assertStatus(422);
    }

    // --- User Bookings ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_list_own_bookings()
    {
        $permit = Permit::factory()->create();
        Booking::factory()->count(3)->create([
            'permit_id' => $permit->id,
            'user_id' => $this->regularUser->id,
        ]);
        // Another user's bookings
        Booking::factory()->count(2)->create(['permit_id' => $permit->id]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/bookings/mine');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_can_cancel_own_pending_booking()
    {
        $booking = Booking::factory()->create([
            'user_id' => $this->regularUser->id,
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->putJson("/api/bookings/{$booking->short_id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_cannot_cancel_another_users_booking()
    {
        $booking = Booking::factory()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->putJson("/api/bookings/{$booking->short_id}/cancel");

        $response->assertStatus(403);
    }

    // --- Filtering ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_filter_bookings_by_status()
    {
        $permit = Permit::factory()->create();
        Booking::factory()->count(3)->create(['permit_id' => $permit->id, 'status' => 'pending']);
        Booking::factory()->count(2)->approved()->create(['permit_id' => $permit->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->getJson('/api/admin/bookings?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_filter_bookings_by_permit()
    {
        $permit1 = Permit::factory()->create();
        $permit2 = Permit::factory()->create();
        Booking::factory()->count(3)->create(['permit_id' => $permit1->id]);
        Booking::factory()->count(2)->create(['permit_id' => $permit2->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->getJson("/api/admin/bookings?permit_id={$permit1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    // --- Season Enforcement ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_book_outside_permit_season()
    {
        // Season: May–September (05-01 to 09-30), book in January
        $permit = Permit::factory()->create([
            'has_season' => true,
            'season_start' => '05-01',
            'season_end' => '09-30',
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => '2027-01-15', // January — outside season
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'This date is outside the permit season.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_book_within_permit_season()
    {
        Mail::fake();

        // Season: April–October
        $permit = Permit::factory()->create([
            'has_season' => true,
            'season_start' => '04-01',
            'season_end' => '10-31',
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => '2027-06-15', // June — inside season
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function wrap_around_season_allows_booking_in_valid_months()
    {
        Mail::fake();

        // Season: April–March (wraps around year — e.g. bat exclusion in summer)
        // Actually this would be April through March which is almost the whole year.
        // Let's do Oct–Mar: open winter/spring, closed summer
        $permit = Permit::factory()->create([
            'has_season' => true,
            'season_start' => '10-01',
            'season_end' => '03-31',
        ]);

        // December is within Oct–Mar wrap-around season
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => '2026-12-15',
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function wrap_around_season_blocks_booking_outside_season()
    {
        // Season: Oct–Mar (wrap-around), July is outside
        $permit = Permit::factory()->create([
            'has_season' => true,
            'season_start' => '10-01',
            'season_end' => '03-31',
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => '2027-07-10', // July — outside season
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'This date is outside the permit season.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function permit_without_season_allows_any_date()
    {
        Mail::fake();

        $permit = Permit::factory()->create(['has_season' => false]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson("/api/permits/{$permit->slug}/bookings", [
                'date' => '2027-07-10',
                'participants' => 1,
                'conditions_accepted' => true,
            ]);

        $response->assertStatus(201);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_create_permit_with_season()
    {
        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson('/api/admin/permits', [
                'name' => 'Seasonal Cave Permit',
                'has_season' => true,
                'season_start' => '04-01',
                'season_end' => '10-31',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['has_season' => true])
            ->assertJsonFragment(['season_start' => '04-01'])
            ->assertJsonFragment(['season_end' => '10-31']);

        $this->assertDatabaseHas('permits', [
            'name' => 'Seasonal Cave Permit',
            'season_start' => '04-01',
            'season_end' => '10-31',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_update_permit_season()
    {
        $permit = Permit::factory()->create();
        $permit->officers()->attach($this->accessOfficer->id);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/permits/{$permit->slug}", [
                'has_season' => true,
                'season_start' => '10-01',
                'season_end' => '03-31',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['season_start' => '10-01'])
            ->assertJsonFragment(['season_end' => '03-31']);
    }

    // --- Admin Manual Booking ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_manually_create_booking()
    {
        Mail::fake();

        $permit = Permit::factory()->create();

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson('/api/admin/bookings', [
                'permit_slug' => $permit->slug,
                'user_id' => $this->regularUser->id,
                'date' => now()->addDays(5)->format('Y-m-d'),
                'participants' => 3,
                'notes' => 'Phone booking',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'approved');

        $this->assertDatabaseHas('bookings', [
            'permit_id' => $permit->id,
            'user_id' => $this->regularUser->id,
            'status' => 'approved',
        ]);

        Mail::assertQueued(BookingApprovedMail::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regular_user_cannot_manually_create_booking()
    {
        $permit = Permit::factory()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/admin/bookings', [
                'permit_slug' => $permit->slug,
                'user_id' => $this->regularUser->id,
                'date' => now()->addDays(5)->format('Y-m-d'),
                'participants' => 1,
            ]);

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function manual_booking_creation_validates_required_fields()
    {
        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson('/api/admin/bookings', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['permit_slug', 'date', 'participants']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_manually_create_booking_without_user()
    {
        $permit = Permit::factory()->create();

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson('/api/admin/bookings', [
                'permit_slug' => $permit->slug,
                'date' => now()->addDays(3)->format('Y-m-d'),
                'participants' => 2,
                'notes' => 'Walk-in booking — no Subterra account',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('applicant', null);

        $this->assertDatabaseHas('bookings', [
            'permit_id' => $permit->id,
            'user_id' => null,
            'status' => 'approved',
        ]);
    }

    // --- Admin Message Applicant ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_message_applicant()
    {
        Mail::fake();

        $permit = Permit::factory()->create();
        $booking = Booking::factory()->create([
            'permit_id' => $permit->id,
            'user_id' => $this->regularUser->id,
        ]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson("/api/admin/bookings/{$booking->short_id}/message", [
                'message' => 'Please bring a wetsuit.',
            ]);

        $response->assertStatus(200);

        Mail::assertQueued(\App\Mail\BookingMessageMail::class, function ($mail) {
            return $mail->hasTo($this->regularUser->email);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function message_requires_message_field()
    {
        $booking = Booking::factory()->create(['user_id' => $this->regularUser->id]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->postJson("/api/admin/bookings/{$booking->short_id}/message", []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    // --- Admin Cancel Booking ---

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_cancel_booking()
    {
        $permit = Permit::factory()->create();
        $booking = Booking::factory()->approved()->create([
            'permit_id' => $permit->id,
            'user_id' => $this->regularUser->id,
        ]);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function access_officer_can_cancel_pending_booking()
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'cancelled');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_cancel_already_rejected_booking()
    {
        $booking = Booking::factory()->create(['status' => 'rejected']);

        $response = $this->actingAs($this->accessOfficer, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/cancel");

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regular_user_cannot_admin_cancel_booking()
    {
        $booking = Booking::factory()->approved()->create([
            'user_id' => $this->regularUser->id,
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->putJson("/api/admin/bookings/{$booking->short_id}/cancel");

        $response->assertStatus(403);
    }
}
