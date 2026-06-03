<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Abstraction over an outbound SMS provider so the concrete vendor (currently Twilio)
 * can be swapped or mocked without touching call sites.
 */
interface SmsSender
{
    /**
     * Send an SMS. Returns true if the provider accepted the message, false otherwise.
     * Implementations must NOT throw on provider/network failure — they log and return
     * false so the safety-critical alert loop is never aborted by one bad send.
     */
    public function send(string $to, string $message): bool;
}
