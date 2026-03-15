<?php

namespace Tests\Feature;

use App\Mail\SuggestionApprovedMail;
use App\Models\Cave;
use App\Models\Club;
use App\Models\SuggestedEdit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Tests\TestCase;

class SuggestedEditTest extends TestCase
{
    use RefreshDatabase;

    private function createApprovedUser()
    {
        $user = User::factory()->withApprovedClub()->create();
        $club = Club::factory()->create();
        $user->clubs()->attach($club, ['status' => 'approved']);

        return $user;
    }

    public function test_user_can_submit_suggestion()
    {
        $user = $this->createApprovedUser();
        $cave = Cave::factory()->create(['description' => 'Original Description']);

        $response = $this->actingAs($user)
            ->postJson('/api/suggested-edits', [
                'suggestable_type' => 'cave',
                'suggestable_id' => $cave->id,
                'original_data' => $cave->toArray(),
                'suggested_data' => array_merge($cave->toArray(), ['description' => 'New Description']),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('suggested_edits', [
            'user_id' => $user->id,
            'suggestable_id' => $cave->id,
            'suggestable_type' => Cave::class, // The controller maps 'cave' to the class
            'status' => 'pending',
        ]);

        $suggestion = SuggestedEdit::first();
        $this->assertEquals('New Description', $suggestion->suggested_data['description']);
    }

    public function test_admin_can_approve_suggestion()
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create(['description' => 'Original Description']);

        $suggestion = SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => $cave->toArray(),
            'suggested_data' => array_merge($cave->toArray(), ['description' => 'Approved Description']),
            'status' => 'pending',
        ]);

        Mail::fake();

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve");

        $response->assertStatus(200);

        Mail::assertQueued(SuggestionApprovedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $this->assertDatabaseHas('suggested_edits', [
            'id' => $suggestion->id,
            'status' => 'approved',
        ]);

        $this->assertEquals('Approved Description', $cave->fresh()->description);
    }

    public function test_admin_can_reject_suggestion()
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create(['description' => 'Original Description']);

        $suggestion = SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => $cave->toArray(),
            'suggested_data' => array_merge($cave->toArray(), ['description' => 'Rejected Description']),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/suggested-edits/{$suggestion->id}/reject", [
                'admin_comment' => 'Not accurate',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('suggested_edits', [
            'id' => $suggestion->id,
            'status' => 'rejected',
            'admin_comment' => 'Not accurate',
        ]);

        $this->assertEquals('Original Description', $cave->fresh()->description);
    }

    public function test_user_can_submit_creation_suggestion()
    {
        $user = $this->createApprovedUser();

        $response = $this->actingAs($user)->postJson('/api/suggested-edits', [
            'suggestable_type' => 'cave',
            'suggestable_id' => null,
            'original_data' => null,
            'suggested_data' => [
                'name' => 'New Suggested Cave',
                'description' => 'Description',
                'location_lat' => 1.0,
                'location_lng' => 1.0,
            ],
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('suggested_edits', [
            'user_id' => $user->id,
            'suggestable_type' => Cave::class, // Controller maps 'cave' to class
            'suggestable_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_admin_can_approve_creation_suggestion()
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $system = \App\Models\CaveSystem::factory()->create(); // Ensure we have a system

        $suggestion = SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => null,
            'original_data' => null,
            'suggested_data' => [
                'name' => 'Brand New Cave',
                'description' => 'A new cave description',
                'region' => 'Mendips',
                'cave_system_id' => $system->id,
                'location_name' => 'Mendips',
                'location_country' => 'UK',
                'location_lat' => 51.0,
                'location_lng' => -2.0,
                'location_alt' => 0,
            ],
        ]);

        $response = $this->actingAs($admin)->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('caves', [
            'name' => 'Brand New Cave',
        ]);

        $newCave = Cave::where('name', 'Brand New Cave')->first();

        $this->assertDatabaseHas('suggested_edits', [
            'id' => $suggestion->id,
            'status' => 'approved',
        ]);
    }

    public function test_unapproved_user_cannot_submit_suggestion()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/suggested-edits', [
                'suggestable_type' => 'cave',
                'suggestable_id' => 1,
                'suggested_data' => ['name' => 'New Name'],
            ]);

        $response->assertStatus(403);
    }

    public function test_user_without_club_cannot_submit_suggestion()
    {
        $user = User::factory()->create();
        // No clubs assigned

        $response = $this->actingAs($user)
            ->postJson('/api/suggested-edits', [
                'suggestable_type' => 'cave',
                'suggestable_id' => 1,
                'suggested_data' => ['name' => 'New Name'],
            ]);

        $response->assertStatus(403);
    }

    public function test_approved_user_in_club_can_submit_suggestion()
    {
        $user = User::factory()->withApprovedClub()->create();
        $club = Club::factory()->create();
        $user->clubs()->attach($club, ['status' => 'approved']);

        $cave = Cave::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/suggested-edits', [
                'suggestable_type' => 'cave',
                'suggestable_id' => $cave->id,
                'suggested_data' => ['name' => 'New Name'],
            ]);

        $response->assertStatus(201);
    }

    public function test_media_lifecycle_in_suggestions()
    {
        Storage::fake('media');
        $user = $this->createApprovedUser();
        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create();

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        // 1. Submit suggestion with UploadedFile image
        $response = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->post('/api/suggested-edits', [
            'suggestable_type' => 'cave',
            'suggestable_id' => $cave->id,
            'suggested_data' => [
                'hero_image' => $imageFile,
                'name' => 'Updated Cave Name',
            ],
        ]);

        $response->assertStatus(201);
        $suggestion = SuggestedEdit::latest()->first();
        $pendingPath = $suggestion->suggested_data['hero_image'];

        $this->assertStringContainsString('pending_edits/', $pendingPath);
        Storage::disk('media')->assertExists($pendingPath);

        // 2. Approve suggestion -> verify file moved
        $this->actingAs($admin)->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve");

        $cave->refresh();
        $finalPath = $cave->heroImage?->filename;

        $this->assertStringContainsString('caves/', $finalPath);
        Storage::disk('media')->assertExists($finalPath);
        Storage::disk('media')->assertMissing($pendingPath);

        // 3. Test rejection cleanup
        $suggestion2 = SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'suggested_data' => [
                'hero_image' => 'pending_edits/cave/another_test_image.webp',
                'name' => 'Another Name',
            ],
            'status' => 'pending',
        ]);
        Storage::disk('media')->put('pending_edits/cave/another_test_image.webp', 'fake image data');

        $this->actingAs($admin)->postJson("/api/admin/suggested-edits/{$suggestion2->id}/reject", [
            'admin_comment' => 'Rejected',
        ]);

        Storage::disk('media')->assertMissing('pending_edits/cave/another_test_image.webp');
    }

    public function test_suggested_edit_filters_non_whitelisted_fields()
    {
        SlackAlert::fake();
        $user = User::factory()->withApprovedClub()->create();

        $payload = [
            'suggestable_type' => 'cave',
            'suggestable_id' => null,
            'suggested_data' => [
                'name' => 'New Cave Name',
                'description' => 'A beautiful new cave.',
                'is_admin_only' => true, // This field is NOT whitelisted
                'internal_notes' => 'Hackers were here', // This field is NOT whitelisted
            ],
            'original_data' => null,
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/suggested-edits', $payload);

        $response->assertStatus(201);

        $suggestion = SuggestedEdit::first();
        $this->assertNotNull($suggestion);

        // Verify that only whitelisted fields are present in the stored data
        $this->assertArrayHasKey('name', $suggestion->suggested_data);
        $this->assertArrayHasKey('description', $suggestion->suggested_data);
        $this->assertArrayNotHasKey('is_admin_only', $suggestion->suggested_data);
        $this->assertArrayNotHasKey('internal_notes', $suggestion->suggested_data);

        $this->assertEquals('New Cave Name', $suggestion->suggested_data['name']);
    }

    public function test_suggested_edit_rejects_arbitrary_suggestable_type()
    {
        $user = User::factory()->withApprovedClub()->create();

        $response = $this->actingAs($user)->postJson('/api/suggested-edits', [
            'suggestable_type' => 'App\\Models\\User',
            'suggested_data' => ['name' => 'Hacked'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('suggestable_type');
    }

    public function test_suggested_edit_accepts_valid_suggestable_type()
    {
        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/suggested-edits', [
            'suggestable_type' => 'cave',
            'suggestable_id' => $cave->id,
            'suggested_data' => ['name' => 'Updated Cave Name'],
            'original_data' => ['name' => $cave->name],
        ]);

        $response->assertStatus(201);
    }

    public function test_robot_suggestion_can_be_approved_without_sending_email()
    {
        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create(['description' => 'Original Description']);

        // Simulate a robot/sync-generated suggestion with no user_id
        $suggestion = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => $cave->toArray(),
            'suggested_data' => array_merge($cave->toArray(), ['description' => 'Robot Updated Description']),
            'status' => 'pending',
        ]);

        Mail::fake();

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/suggested-edits/{$suggestion->id}/approve");

        $response->assertStatus(200);

        // No email should be sent for robot suggestions
        Mail::assertNotSent(SuggestionApprovedMail::class);
        Mail::assertNothingQueued();

        $this->assertDatabaseHas('suggested_edits', [
            'id' => $suggestion->id,
            'status' => 'approved',
        ]);

        $this->assertEquals('Robot Updated Description', $cave->fresh()->description);
    }

    public function test_robot_suggestion_can_be_rejected()
    {
        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create(['description' => 'Original Description']);

        $suggestion = SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => $cave->toArray(),
            'suggested_data' => array_merge($cave->toArray(), ['description' => 'Robot Rejected Description']),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/suggested-edits/{$suggestion->id}/reject", [
                'admin_comment' => 'Incorrect robot data',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('suggested_edits', [
            'id' => $suggestion->id,
            'status' => 'rejected',
            'admin_comment' => 'Incorrect robot data',
        ]);

        $this->assertEquals('Original Description', $cave->fresh()->description);
    }

    public function test_admin_index_includes_robot_suggestions()
    {
        $admin = User::factory()->admin()->create();
        $cave = Cave::factory()->create();

        SuggestedEdit::create([
            'user_id' => null,
            'suggestable_type' => Cave::class,
            'suggestable_id' => $cave->id,
            'original_data' => $cave->toArray(),
            'suggested_data' => ['description' => 'Robot suggestion'],
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/suggested-edits?status=pending');

        $response->assertStatus(200);
        $response->assertJsonFragment(['user_id' => null]);
    }
}
