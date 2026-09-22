<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActiveCalloutsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function active_callouts_list_does_not_expose_the_cancel_capability_id(): void
    {
        $owner = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/callouts/active')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertArrayNotHasKey('id', $response->json('data.0'));
        $this->assertStringNotContainsString($callout->id, $response->getContent());
    }
}
