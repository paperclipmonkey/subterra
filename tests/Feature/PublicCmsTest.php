<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCmsTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_public_access_to_cms_pages()
    {
        $user = User::factory()->create();
        $page = Page::create([
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'content' => 'Keep your data safe.',
            'user_id' => $user->id,
        ]);

        // Access WITHOUT actingAs
        $response = $this->getJson('/api/pages/privacy-policy');

        $response->assertOk();
        $response->assertJsonFragment([
            'title' => 'Privacy Policy',
            'content' => 'Keep your data safe.',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_404_for_non_existent_public_page()
    {
        $response = $this->getJson('/api/pages/non-existent');
        $response->assertNotFound();
    }
}
