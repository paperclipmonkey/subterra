<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JsonSchemaValidator;
use Tests\TestCase;

class TagsTest extends TestCase
{
    use RefreshDatabase;
    use JsonSchemaValidator;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_array_when_tags_exist()
    {
        $this->actingAs(User::factory()->create());
        Tag::factory()->count(3)->create();
        $response = $this->getJson('/api/tags');

        $response->assertOk();
        $this->assertResponseMatchesSchema($response, 'endpoints/tags-index');
    }
}
