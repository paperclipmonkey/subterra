<?php
Namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Support\JsonSchemaValidator;
use App\Models\User;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TagsTest extends TestCase
{

    use RefreshDatabase, JsonSchemaValidator;

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