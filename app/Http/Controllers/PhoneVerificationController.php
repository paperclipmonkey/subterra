<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\UserDetailEmailResource;
use App\Services\Sms\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function __construct(private readonly PhoneVerificationService $verification)
    {
    }

    /** SMS a fresh verification code to the authenticated user's phone number. */
    public function sendCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (empty($user->phone)) {
            return response()->json(['message' => 'Add a phone number to your profile before verifying it.'], 422);
        }

        if ($user->phone_verified_at !== null) {
            return response()->json(['message' => 'Your phone number is already verified.']);
        }

        if (!$this->verification->sendCode($user)) {
            return response()->json(['message' => 'We could not send a verification code right now. Please try again shortly.'], 502);
        }

        return response()->json(['message' => 'We have sent a verification code to your phone.']);
    }

    /** Confirm the code the user received and mark their number verified. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $user = $request->user();

        if (!$this->verification->verify($user, $data['code'])) {
            return response()->json([
                'message' => 'That code is incorrect or has expired. Request a new one and try again.',
            ], 422);
        }

        return response()->json([
            'message' => 'Your phone number is verified.',
            'data' => new UserDetailEmailResource($user->fresh()),
        ]);
    }
}
