<?php
Namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsTest extends TestCase
{

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_array_when_no_news_files_exist()
    {
        Storage::fake('news');

        $response = $this->getJson('/api/news');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_news_content_from_files()
    {
        Storage::fake('news');
        Storage::disk('news')->put('first-news.md', 'First news content');
        Storage::disk('news')->put('second-news.md', 'Second news content');

        $response = $this->getJson('/api/news');

        $response->assertOk();
        $json = $response->json();

        // The files should be reversed, so 'second-news' comes first
        $this->assertEquals([
            'second-news' => 'Second news content',
            'first-news' => 'First news content',
        ], $json);
    }
}