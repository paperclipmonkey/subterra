<?php
Namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TagsTest extends TestCase
{

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_array_when_tags_exist()
    {
        \App\Models\Tag::factory()->count(3)->create();
        $response = $this->getJson('/api/tags');

        $response->assertOk();
    }

}