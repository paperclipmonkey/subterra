<?php

declare(strict_types=1);

namespace App\Services\Sms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads the remaining credit on the two SMS providers — Twilio (primary) and TextMagic
 * (backup) — so the dashboard can surface it and callout creation can refuse to run when
 * either is out of credit.
 *
 * Balances are cached (config callouts.balance.cache_seconds) so the provider APIs stay off
 * the callout-creation hot path. A balance we can't read is reported as null/unreachable and
 * never counts as "below minimum" — a balance-API outage must not block callouts.
 */
class SmsBalanceService
{
    private const CACHE_PRIMARY = 'sms_balance.primary';

    private const CACHE_BACKUP = 'sms_balance.backup';

    /**
     * Per-provider status for display + guarding.
     *
     * @return array{primary: array, backup: array}
     */
    public function providerStatuses(): array
    {
        $primaryMin = (float) config('callouts.balance.primary_min', 0);
        $backupMin = (float) config('callouts.balance.backup_min', 0);

        return [
            'primary' => $this->describe('Twilio', $this->twilioBalance(), $primaryMin),
            'backup' => $this->describe('TextMagic', $this->textmagicBalance(), $backupMin),
        ];
    }

    /**
     * Keys ('primary'/'backup') of providers that are reachable AND below their minimum.
     * Unknown/unreachable balances are deliberately excluded.
     *
     * @return array<int, string>
     */
    public function blockingProviders(): array
    {
        $blocking = [];

        foreach ($this->providerStatuses() as $key => $status) {
            if ($status['reachable'] && !$status['ok']) {
                $blocking[] = $key;
            }
        }

        return $blocking;
    }

    /** Drop the cached balances so the next read is live (e.g. after topping up). */
    public function refresh(): void
    {
        Cache::forget(self::CACHE_PRIMARY);
        Cache::forget(self::CACHE_BACKUP);
    }

    /**
     * @param  array{amount: float, currency: ?string}|null  $balance
     */
    private function describe(string $provider, ?array $balance, float $minimum): array
    {
        $reachable = $balance !== null;
        $amount = $balance['amount'] ?? null;

        return [
            'provider' => $provider,
            'amount' => $amount,
            'currency' => $balance['currency'] ?? null,
            'minimum' => $minimum,
            'reachable' => $reachable,
            // ok = we read a balance and it is at or above the minimum.
            'ok' => $reachable && $amount >= $minimum,
        ];
    }

    /**
     * @return array{amount: float, currency: ?string}|null
     */
    private function twilioBalance(): ?array
    {
        $sid = (string) config('services.twilio.sid');
        $token = (string) config('services.twilio.token');

        if ($sid === '' || $token === '') {
            return null;
        }

        return Cache::remember(self::CACHE_PRIMARY, $this->ttl(), function () use ($sid, $token) {
            try {
                $response = Http::withBasicAuth($sid, $token)
                    ->timeout(8)
                    ->retry(2, 200, throw: false)
                    ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Balance.json");

                if (!$response->successful()) {
                    Log::warning('Twilio balance read failed: '.$response->status());

                    return;
                }

                return [
                    'amount' => (float) $response->json('balance'),
                    'currency' => $response->json('currency'),
                ];
            } catch (\Throwable $e) {
                Log::warning('Twilio balance exception: '.$e->getMessage());

                return;
            }
        });
    }

    /**
     * @return array{amount: float, currency: ?string}|null
     */
    private function textmagicBalance(): ?array
    {
        $username = (string) config('services.textmagic.username');
        $apiKey = (string) config('services.textmagic.api_key');

        if ($username === '' || $apiKey === '') {
            return null;
        }

        return Cache::remember(self::CACHE_BACKUP, $this->ttl(), function () use ($username, $apiKey) {
            try {
                $response = Http::withHeaders([
                    'X-TM-Username' => $username,
                    'X-TM-Key' => $apiKey,
                ])
                    ->timeout(8)
                    ->retry(2, 200, throw: false)
                    ->get('https://rest.textmagic.com/api/v2/user');

                if (!$response->successful()) {
                    Log::warning('TextMagic balance read failed: '.$response->status());

                    return;
                }

                // TextMagic returns currency as an object (e.g. {"id": "GBP", "htmlSymbol": "&pound;"}),
                // not a plain string like Twilio. Reduce it to the currency code so the frontend
                // doesn't render "[object Object]".
                $currency = $response->json('currency');
                if (is_array($currency)) {
                    $currency = $currency['id'] ?? $currency['code'] ?? null;
                }

                return [
                    'amount' => (float) $response->json('balance'),
                    'currency' => is_string($currency) ? $currency : null,
                ];
            } catch (\Throwable $e) {
                Log::warning('TextMagic balance exception: '.$e->getMessage());

                return;
            }
        });
    }

    private function ttl(): int
    {
        return max(0, (int) config('callouts.balance.cache_seconds', 300));
    }
}
