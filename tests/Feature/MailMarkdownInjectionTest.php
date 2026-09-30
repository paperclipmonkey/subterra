<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\PlaceholderUserNoticeMail;
use App\Models\User;
use App\Support\MailMarkdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Markdown mail templates are parsed as Markdown after Blade renders them, so
 * user-supplied text must not be able to inject links into Subterra emails.
 */
class MailMarkdownInjectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_hostile_name_cannot_inject_a_link_into_the_placeholder_notice(): void
    {
        $creator = User::factory()->create(['name' => '**Subterra Security** [Reset your account](https://evil.example/c)']);
        $placeholder = User::factory()->create(['name' => '[Verify your account](https://evil.example)']);

        $html = (new PlaceholderUserNoticeMail($placeholder, $creator))->render();

        $this->assertStringNotContainsString('href="https://evil.example', $html);
        $this->assertStringNotContainsString('<strong>Subterra Security</strong>', $html);
        // The text still reads as typed.
        $this->assertStringContainsString('[Verify your account](https://evil.example)', $html);
    }

    #[Test]
    public function escape_neutralises_link_emphasis_and_code_syntax(): void
    {
        $this->assertSame('\\[a\\](b) \\*c\\* \\`d\\` \\\\', MailMarkdown::escape('[a](b) *c* `d` \\'));
        $this->assertSame('john_smith@example.com', MailMarkdown::escape('john_smith@example.com'));
        $this->assertSame('', MailMarkdown::escape(null));
    }
}
