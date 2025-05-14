<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubMembersTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $adminUser;
    private User $approvedMember1;
    private User $approvedMember2;
    private User $pendingMember;
    private User $nonMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->adminUser = User::factory()->create(['is_admin' => true]);
        $this->approvedMember1 = User::factory()->create();
        $this->approvedMember2 = User::factory()->create();
        $this->pendingMember = User::factory()->create();
        $this->nonMember = User::factory()->create();

        $this->club->users()->attach($this->approvedMember1, ['status' => 'approved']);
        $this->club->users()->attach($this->approvedMember2, ['status' => 'approved']);
        $this->club->users()->attach($this->pendingMember, ['status' => 'pending']);
    }

    private function getEndpointUrl(): string
    {
        return "/api/clubs/{$this->club->slug}/members";
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_access_members(): void
    {
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertUnauthorized();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_member_cannot_access_members(): void
    {
        $this->actingAs($this->nonMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pending_member_cannot_access_members(): void
    {
        $this->actingAs($this->pendingMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approved_member_can_access_members(): void
    {
        $this->actingAs($this->approvedMember1, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'photo']
            ]
        ]);
        // The controller should only return approved members.
        $response->assertJsonCount(2, 'data');
        $response->assertJsonFragment(['id' => $this->approvedMember1->id]);
        $response->assertJsonFragment(['id' => $this->approvedMember2->id]);
        $response->assertJsonMissing(['id' => $this->pendingMember->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_user_can_access_members(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'photo']
            ]
        ]);
        $response->assertJsonCount(2, 'data');
    }
}
