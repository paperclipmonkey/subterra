<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Resources\CaveResource;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaveResourceSystemTagsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function system_tags_are_reloaded_for_each_request(): void
    {
        $this->assertNull(CaveResource::getCachedTag('> 5km'));
        $tag = Tag::factory()->create(['tag' => '> 5km']);

        Once::flush();

        $this->assertTrue($tag->is(CaveResource::getCachedTag('> 5km')));
    }
}
