<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Escape user-supplied text for interpolation into a Markdown mail template.
 *
 * Blade's {{ }} escapes HTML, but markdown mailables are parsed as Markdown
 * *after* Blade renders them, so a name like "[Verify your account](https://…)"
 * became a real link in a Subterra-branded email. Placeholder-user notices go to
 * any address a member types in, which made that a phishing vector.
 *
 * Only the characters that build links, images, emphasis and code spans are
 * escaped, to keep the plain-text part (the same Markdown, unparsed) readable.
 */
final class MailMarkdown
{
    public static function escape(?string $text): string
    {
        return preg_replace('/([\\\\\[\]*`])/', '\\\\$1', (string) $text);
    }
}
