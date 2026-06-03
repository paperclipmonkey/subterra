<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Abstraction over an outbound voice-call provider (currently Twilio).
 */
interface VoiceCaller
{
    /**
     * Place an outbound voice call. The provider fetches TwiML from $twimlUrl to drive
     * the call (spoken message + "press 1 to acknowledge" gather).
     *
     * Returns the provider call SID on success, or null on failure. Implementations must
     * NOT throw — they log and return null.
     */
    public function call(string $to, string $twimlUrl): ?string;
}
