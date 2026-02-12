<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_pages_index()
    {
        $admin = User::factory()->admin()->create();
        Page::factory()->create(['title' => 'Page 1']);
        Page::factory()->create(['title' => 'Page 2']);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/pages');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_create_page()
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'title' => 'Test Page',
            'slug' => 'test-page',
            'content' => '# Hello World',
        ];

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/pages', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Test Page');

        $this->assertDatabaseHas('pages', ['slug' => 'test-page']);
    }

    public function test_admin_can_update_page()
    {
        $admin = User::factory()->admin()->create();
        $page = Page::factory()->create();

        $payload = [
            'title' => 'Updated Page',
            'slug' => $page->slug,
            'content' => 'Updated content',
        ];

        $response = $this->actingAs($admin)
            ->putJson("/api/admin/pages/{$page->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Updated Page');

        $this->assertDatabaseHas('pages', ['title' => 'Updated Page']);
    }

    public function test_admin_can_delete_page()
    {
        $admin = User::factory()->admin()->create();
        $page = Page::factory()->create();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/admin/pages/{$page->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_non_admin_cannot_manage_pages()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
            ->getJson('/api/admin/pages');
        $response->assertStatus(403);

        $response = $this->actingAs($user)
            ->postJson('/api/admin/pages', []);
        $response->assertStatus(403);
    }

    public function test_public_can_view_page_by_slug()
    {
        $user = User::factory()->create(); // Authenticated user
        $page = Page::factory()->create(['access_count' => 0]);

        $response = $this->actingAs($user)
            ->getJson("/api/pages/{$page->slug}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => $page->title]);

        $this->assertDatabaseHas('pages', [
            'id' => $page->id,
            'access_count' => 1
        ]);
    }
}
