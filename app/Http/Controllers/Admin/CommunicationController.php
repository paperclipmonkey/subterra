<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PlatformNews;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CommunicationController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'test_mode' => 'boolean',
        ]);

        $subject = $validated['subject'];
        $body = $validated['body'];
        $isTestMode = $request->boolean('test_mode');

        if ($isTestMode) {
            $user = $request->user();
            Mail::to($user)->queue(new PlatformNews($subject, $body, $user));

            return response()->json(['message' => "Test email sent to {$user->email}"]);
        }

        // Production Send
        $count = 0;
        User::where('email_platform_news', true)
            ->chunk(100, function ($users) use ($subject, $body, &$count) {
                foreach ($users as $user) {
                    Mail::to($user)->queue(new PlatformNews($subject, $body, $user));
                    ++$count;
                }
            });

        return response()->json(['message' => "Queued emails for {$count} users."]);
    }
}
