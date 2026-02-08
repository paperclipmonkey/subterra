<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitCorrectionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class CorrectionController extends Controller
{
    public function store(SubmitCorrectionRequest $request)
    {
        $user = $request->user();

        if (!$user->is_admin) {
            if (!$user->is_approved) {
                abort(403, 'Your account must be approved before you can report issues.');
            }

            if ($user->clubs()->count() === 0) {
                abort(403, 'You must be a member of at least one club to report issues.');
            }
        }
        $user = $request->user();
        $correction = $request->validated('correction');
        $entityName = $request->validated('entity_name');
        $entityType = $request->validated('entity_type');
        $url = $request->validated('url');

        $message = "🚨 *Factual Correction Submitted*\n\n" .
            "*User:* {$user->name} ({$user->email})\n" .
            "*Entity:* {$entityName} ({$entityType})\n" .
            "*Page:* " . $url . "\n\n" .
            "*Correction Details:*\n" .
            "> " . str_replace("\n", "\n> ", $correction);

        try {
            SlackAlert::to('corrections')->message($message);
        } catch (\Exception $e) {
            Log::error('Failed to send Correction Slack alert: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Correction submitted successfully']);
    }
}
