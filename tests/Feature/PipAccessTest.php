<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PipFeedback;
use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipAccessTest extends TestCase
{
    use RefreshDatabase;

    private const CHAT = '/api/assistant/chat';
    private const AGREEMENT = '/api/assistant/agreement';
    private const FEEDBACK = '/api/assistant/feedback';
    private const ADMIN_USERS = '/api/admin/users';

    private function validChatPayload(): array
    {
        return [
            'messages' => [
                ['role' => 'user', 'content' => 'Hi Pip'],
            ],
        ];
    }

    private function bindEmptyAssistantService(): void
    {
        $this->mock(AssistantService::class, function ($mock) {
            $mock->shouldReceive('chat')->andReturn('ok');
        });
    }

    // -------------------------------------------------------------------------
    // Access gate
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_chat_request_is_rejected(): void
    {
        $this->postJson(self::CHAT, $this->validChatPayload())->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regular_user_without_pip_access_cannot_chat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(self::CHAT, $this->validChatPayload())
            ->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function data_admin_alone_cannot_chat(): void
    {
        $user = User::factory()->dataAdmin()->create();

        $this->actingAs($user)
            ->postJson(self::CHAT, $this->validChatPayload())
            ->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_with_pip_access_role_can_chat_after_signing_agreement(): void
    {
        $this->bindEmptyAssistantService();

        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $this->actingAs($user)
            ->postJson(self::CHAT, $this->validChatPayload())
            ->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_with_pip_access_must_sign_agreement_before_chatting(): void
    {
        $user = User::factory()->pipAccess()->create();

        $response = $this->actingAs($user)
            ->postJson(self::CHAT, $this->validChatPayload());

        $response->assertStatus(403)
            ->assertJsonPath('code', 'pip_agreement_required');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_admin_also_needs_to_sign_agreement_before_chatting(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson(self::CHAT, $this->validChatPayload());

        $response->assertStatus(403)
            ->assertJsonPath('code', 'pip_agreement_required');
    }

    // -------------------------------------------------------------------------
    // Agreement endpoint
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function accepting_agreement_records_a_timestamp(): void
    {
        $user = User::factory()->pipAccess()->create();
        $this->assertNull($user->pip_agreement_signed_at);

        $this->actingAs($user)
            ->postJson(self::AGREEMENT)
            ->assertStatus(200)
            ->assertJsonStructure(['pip_agreement_signed_at']);

        $this->assertNotNull($user->fresh()->pip_agreement_signed_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function agreement_endpoint_rejects_users_without_pip_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(self::AGREEMENT)
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Feedback endpoint
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function feedback_endpoint_requires_pip_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(self::FEEDBACK, [
                'rating' => -1,
                'messages' => [['role' => 'user', 'content' => 'hi']],
            ])
            ->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function thumbs_down_feedback_persists_transcript(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $payload = [
            'rating' => -1,
            'comment' => 'Completely wrong',
            'messages' => [
                ['role' => 'user', 'content' => 'Recommend a cave'],
                ['role' => 'assistant', 'content' => 'Try the moon.'],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson(self::FEEDBACK, $payload);

        $response->assertStatus(201);

        $this->assertDatabaseCount('pip_feedback', 1);
        $stored = PipFeedback::first();
        $this->assertSame(-1, $stored->rating);
        $this->assertSame('Completely wrong', $stored->comment);
        $this->assertCount(2, $stored->transcript);
        $this->assertSame('Try the moon.', $stored->rated_reply);
        $this->assertSame($user->id, $stored->user_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function feedback_validates_rating_value(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $this->actingAs($user)
            ->postJson(self::FEEDBACK, [
                'rating' => 5,
                'messages' => [['role' => 'user', 'content' => 'hi']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    // -------------------------------------------------------------------------
    // Admin user panel — toggle Pip access role
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_admin_can_grant_pip_access_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$target->id}/toggle-role/pip_access")
            ->assertStatus(200);

        $this->assertTrue($target->fresh()->hasRole('pip_access'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_admin_can_revoke_pip_access_role(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->pipAccess()->create();

        $this->actingAs($admin)
            ->putJson("/api/admin/users/{$target->id}/toggle-role/pip_access")
            ->assertStatus(200);

        $this->assertFalse($target->fresh()->hasRole('pip_access'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admin_cannot_grant_pip_access_role(): void
    {
        $regular = User::factory()->create();
        $target = User::factory()->create();

        $this->actingAs($regular)
            ->putJson("/api/admin/users/{$target->id}/toggle-role/pip_access")
            ->assertStatus(403);

        $this->assertFalse($target->fresh()->hasRole('pip_access'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_with_only_pip_access_is_not_treated_as_admin(): void
    {
        // Pip access is a feature flag, not an admin role. Granting it must
        // not allow the user into the admin user listing.
        $pipUser = User::factory()->pipAccess()->create();

        $this->assertFalse($pipUser->fresh()->is_admin, 'pip_access alone should not flip is_admin');

        $this->actingAs($pipUser)
            ->getJson(self::ADMIN_USERS)
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Admin Pip feedback review
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_list_flagged_feedback(): void
    {
        $admin = User::factory()->admin()->create();
        $down = PipFeedback::create([
            'user_id' => $admin->id,
            'rating' => -1,
            'transcript' => [['role' => 'user', 'content' => 'x']],
            'rated_reply' => 'bad',
        ]);
        PipFeedback::create([
            'user_id' => $admin->id,
            'rating' => 1,
            'transcript' => [['role' => 'user', 'content' => 'y']],
            'rated_reply' => 'good',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/pip-feedback')
            ->assertStatus(200);

        $items = $response->json('data');
        $this->assertCount(1, $items, 'Default listing should show only thumbs-down rows.');
        $this->assertSame($down->id, $items[0]['id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admin_cannot_access_feedback_listing(): void
    {
        $user = User::factory()->pipAccess()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/pip-feedback')
            ->assertStatus(403);
    }
}
