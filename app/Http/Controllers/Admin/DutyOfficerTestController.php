<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\SmsSender;
use App\Contracts\VoiceCaller;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Lets duty officers test the alerting channels — to their own phone, or (to build
 * confidence in the whole rota) to every duty officer at once. Sends a real test SMS and
 * places a real test voice call so a DO can confirm their phone rings and that any
 * Do-Not-Disturb / Emergency Bypass overrides are configured correctly.
 */
class DutyOfficerTestController extends Controller
{
    public function __construct(
        private readonly SmsSender $sms,
        private readonly VoiceCaller $voice,
    ) {
    }

    /** Send a test SMS + voice call to the current user's own phone. */
    public function testSelf(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->phone)) {
            return response()->json(['message' => 'You have no phone number on your profile. Add one before testing.'], 422);
        }

        $results = $this->sendTo(collect([$user]));

        return response()->json([
            'message' => "Test SMS and voice call sent to your phone ({$user->phone}). It may take a moment to arrive.",
            'results' => $results,
        ]);
    }

    /** Send a test SMS + voice call to every active duty officer with a phone number. */
    public function testBroadcast(Request $request): JsonResponse
    {
        $dutyOfficers = User::whereHas('roles', fn ($q) => $q->where('slug', 'duty_officer'))
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->get();

        if ($dutyOfficers->isEmpty()) {
            return response()->json(['message' => 'No active duty officers with a phone number were found.'], 422);
        }

        $results = $this->sendTo($dutyOfficers);

        return response()->json([
            'message' => "Test SMS and voice call sent to {$dutyOfficers->count()} duty officer(s).",
            'results' => $results,
        ]);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, array{user: string, sms: bool, call: bool}>
     */
    private function sendTo(Collection $users): array
    {
        $secret = (string) config('services.twilio.webhook_secret') ?: 'unconfigured';
        $twimlUrl = route('webhooks.twilio.voice.test', ['secret' => $secret]);
        $message = 'Subterra test: this confirms you can receive callout SMS alerts. No action needed.';

        return $users->map(fn (User $u) => [
            'user' => $u->name,
            'sms' => $this->sms->send($u->phone, $message),
            'call' => $this->voice->call($u->phone, $twimlUrl) !== null,
        ])->all();
    }
}
