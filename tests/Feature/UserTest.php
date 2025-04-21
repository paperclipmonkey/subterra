<?php
Namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_collection_of_users()
    {
        $users = User::factory()->count(3)->create();

        $response = $this->getJson(route('users.index'));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_a_user_if_not_exists()
    {
        Storage::fake('media');

        $payload = [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];

        $response = $this->postJson(route('users.create'), $payload);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'is_active' => false,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_user_detail_resource()
    {
        $user = User::factory()->create();

        $response = $this->getJson(route('users.show', $user));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $user->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_user_bio()
    {
        $user = User::factory()->create([
            'bio' => null,
        ]);

        $payload = [
            'bio' => 'I love chess.',
        ];

        $response = $this->putJson(route('users.store', $user), $payload);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bio' => 'I love chess.',
        ]);
    }
}