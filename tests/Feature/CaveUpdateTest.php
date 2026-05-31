<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaveUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_cave_update_handles_uploaded_image_correctly_regression_fix()
    {
        \App\Models\Tag::create(['tag' => 'Previously Done', 'category' => 'Status', 'assignable' => false, 'type' => 'trip']);
        \App\Models\Tag::create(['tag' => 'Not Done Yet', 'category' => 'Status', 'assignable' => false, 'type' => 'trip']);

        $user = User::factory()->dataAdmin()->create();
        $cave = Cave::factory()->create();

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.gif');

        $response = $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/api/caves/'.$cave->slug, [
                '_method' => 'PUT',
                'name' => 'Updated Name',
                'hero_image' => [
                    'data' => $imageFile,
                    'filename' => 'test.gif',
                ],
            ]);

        $response->assertStatus(200);

        // Verify the cave was updated
        $cave->refresh();
        $this->assertEquals('Updated Name', $cave->name);
        // Verify image was processed (path should contain 'caves/' and end with .webp, assuming service works)
        // If the service works, it saves to storage. We can just check it's not the array and not null.
        $this->assertNotNull($cave->heroImage);
        $this->assertStringContainsString('caves/', $cave->heroImage->filename);
        $this->assertStringEndsWith('.webp', $cave->heroImage->filename);
    }
}
