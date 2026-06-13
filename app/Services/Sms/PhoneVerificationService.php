<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Sends a short numeric code by SMS and confirms it, so a user's phone number is known to
 * be correct and reachable. A verified number is required to create a callout or be on the
 * duty-officer rota.
 */
class PhoneVerificationService
{
    public const CODE_TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsSender $sms)
    {
    }

    /**
     * Generate a fresh code, store it (hashed) and SMS it to the user's number.
     * Returns false if there's no number to send to or the SMS provider rejected it.
     */
    public function sendCode(User $user): bool
    {
        if (empty($user->phone)) {
            return false;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'phone_verification_code' => Hash::make($code),
            'phone_verification_sent_at' => now(),
            'phone_verification_attempts' => 0,
        ])->save();

        return $this->sms->send(
            $user->phone,
            "Your Subterra verification code is {$code}. It expires in ".self::CODE_TTL_MINUTES.' minutes.',
            ['user_id' => $user->id, 'recipient_name' => $user->name, 'label' => 'phone_verification'],
        );
    }

    /**
     * Confirm a code. On success the number is marked verified and the code cleared.
     * Reasons for failure: no/expired code, too many attempts, or a wrong code (which
     * increments the attempt counter).
     */
    public function verify(User $user, string $code): bool
    {
        if ($user->phone_verified_at !== null) {
            return true;
        }

        if (empty($user->phone_verification_code) || $user->phone_verification_sent_at === null) {
            return false;
        }

        if ($user->phone_verification_sent_at->lt(now()->subMinutes(self::CODE_TTL_MINUTES))) {
            return false;
        }

        if ($user->phone_verification_attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (!Hash::check($code, $user->phone_verification_code)) {
            $user->increment('phone_verification_attempts');

            return false;
        }

        $user->forceFill([
            'phone_verified_at' => now(),
            'phone_verification_code' => null,
            'phone_verification_sent_at' => null,
            'phone_verification_attempts' => 0,
        ])->save();

        return true;
    }
}
