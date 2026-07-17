<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Scopes\IsActiveScope;
use App\Models\User;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function unsubscribe(Request $request, string $user)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired unsubscribe link.');
        }

        // Resolve manually without the IsActiveScope: a deactivated user's
        // signed unsubscribe link must still work, not 404.
        User::withoutGlobalScope(IsActiveScope::class)
            ->findOrFail($user)
            ->update(['email_platform_news' => false]);

        return 'You have been successfully unsubscribed from platform news.';
    }
}
