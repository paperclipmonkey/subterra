<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Cave;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CaveTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
    public function it_can_get_the_list_of_caves()
    {
        Cave::factory()->count(3)->create();

        $response = $this->get('/api/caves');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_get_a_single_cave_by_slug()
    {
        $cave = Cave::factory()->create([
            'slug' => 'test-cave'
        ]);

        $response = $this->get('/api/caves/' . $cave->slug);

        $response->assertStatus(200);
    }
}