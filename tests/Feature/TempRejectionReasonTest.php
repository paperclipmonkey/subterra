<?php

namespace Tests\Feature;

use App\Mail\ClubAccessRespondedMail;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TempRejectionReasonTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_receives_reason_in_rejection_request()
    {
        Mail::fake();

        $club = Club::factory()->create();
        $user = User::factory()->create(['name' => 'Demo User']);

        $club->users()->attach($user->id, ['status' => 'pending']);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');

        // Pass reason as query param
        $response = $this->putJson("/api/admin/clubs/{$club->slug}/members/{$user->id}/reject?reason=incorrect_name");

        $response->assertOk();
        $response->assertJson(['message' => 'Member rejected.']);

        Mail::assertQueued(ClubAccessRespondedMail::class, function ($mail) use ($user, $club) {
            return $mail->hasTo($user->email) &&
                   $mail->club->id === $club->id &&
                   $mail->status === 'rejected' &&
                   $mail->reason === 'incorrect_name';
        });
    }
}
