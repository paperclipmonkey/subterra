<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Callout;
use App\Models\Incident;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\CalloutService;
use App\Services\IncidentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Twilio inbound webhooks (SMS replies + voice TwiML + DTMF gather).
 *
 * Authenticated by a shared secret embedded in the URL path (Twilio cannot send a custom
 * header) — the same pattern used by the other webhooks in this app. Twilio request
 * signature validation could be layered on later for defence in depth.
 */
class TwilioController extends Controller
{
    public function __construct(
        private readonly CalloutService $calloutService,
        private readonly IncidentService $incidentService,
    ) {
    }

    /** Inbound SMS: "OUT SAFE" (caver) or "ACK" (duty officer) or a free-text note. */
    public function handleSms(Request $request, string $secret): Response
    {
        $this->assertSecret($secret);

        $from = (string) $request->input('From', '');
        $body = trim((string) $request->input('Body', ''));
        $command = Str::upper($body);

        if ($from === '') {
            return $this->twiml('<Message>Missing sender.</Message>');
        }

        Log::info('Twilio inbound SMS', ['from' => $from, 'body' => $body]);

        $normalized = $this->normalizePhone($from);

        // Duty officer acknowledging the active incident.
        if ($command === 'ACK') {
            return $this->handleAck($normalized);
        }

        // Otherwise treat as a caver replying about their own callout.
        $callout = $this->findCalloutByPhone($normalized);

        if (!$callout) {
            return $this->twiml('<Message>No active callout found for this number.</Message>');
        }

        if ($command === 'OUT SAFE') {
            $this->calloutService->cancel($callout, 'SMS');
            $callout->update(['cancelled_location' => 'SMS']);

            if ($callout->incident()->exists()) {
                $callout->incident->notes()->create([
                    'user_id' => null,
                    'content' => "Callout CANCELLED via SMS from {$from} saying 'OUT SAFE'.",
                ]);
                // Deliberately NOT resolved here: a single inbound SMS must never close
                // an open incident mid-rescue. Like the in-app cancel path, the incident
                // stays open for a duty officer to verify and resolve.
            }

            return $this->twiml('<Message>Callout cancelled. Glad you are safe.</Message>');
        }

        // Free-text: log against the incident / callout for the duty officer to see.
        if ($callout->incident) {
            $callout->incident->notes()->create([
                'user_id' => null,
                'content' => "SMS from {$from}: {$body}",
            ]);
        } else {
            $callout->update(['team_details' => trim(($callout->team_details ?? '')."\n[SMS from {$from}]: {$body}")]);
        }

        return $this->twiml('<Message>Message logged. Reply "OUT SAFE" to cancel your callout.</Message>');
    }

    /**
     * Delivery status callback: Twilio POSTs the message SID and its latest status
     * (queued → sent → delivered, or undelivered/failed) so we can track whether alerts
     * actually reached each device.
     */
    public function handleSmsStatus(Request $request, string $secret): Response
    {
        $this->assertSecret($secret);

        $sid = (string) $request->input('MessageSid', $request->input('SmsSid', ''));
        $status = (string) $request->input('MessageStatus', $request->input('SmsStatus', ''));

        if ($sid === '' || $status === '') {
            return response('', 204);
        }

        $message = SmsMessage::where('provider_sid', $sid)->first();

        if (!$message) {
            Log::info('Twilio status callback for unknown SMS SID.', ['sid' => $sid, 'status' => $status]);

            return response('', 204);
        }

        // Callbacks can arrive out of order: never let a late earlier-stage status
        // (e.g. 'sent') regress a terminal one (delivered / failed / undelivered).
        $terminalStatuses = array_merge(['delivered'], SmsMessage::FAILED_STATUSES);

        if (in_array($message->status, $terminalStatuses, true) && !in_array($status, $terminalStatuses, true)) {
            return response('', 204);
        }

        $attributes = ['status' => $status];

        if ($status === 'delivered') {
            $attributes['delivered_at'] = now();
        } elseif (in_array($status, SmsMessage::FAILED_STATUSES, true)) {
            $attributes['failed_at'] = now();
            if ($request->filled('ErrorCode')) {
                $attributes['error_code'] = (string) $request->input('ErrorCode');
            }
        }

        $message->update($attributes);

        return response('', 204);
    }

    /** Voice TwiML for an overdue-incident alert call: speak the alert + press-1 gather. */
    public function voiceTwiml(Request $request, string $secret): Response
    {
        $this->assertSecret($secret);

        $incident = Incident::with('callout.cave')->find($request->query('incident'));
        $cave = $this->xml($incident?->callout?->cave_name ?? 'an unknown location');

        $gatherUrl = $this->xml(route('webhooks.twilio.voice.gather', [
            'secret' => $secret,
            'incident' => $request->query('incident'),
            'user' => $request->query('user'),
        ]));

        return $this->twiml(
            '<Gather input="dtmf" numDigits="1" timeout="10" action="'.$gatherUrl.'" method="POST">'
            .'<Say voice="alice">Subterra emergency alert. A caver is overdue at '.$cave.'. '
            .'Press 1 now to acknowledge and take control of this incident.</Say>'
            .'</Gather>'
            .'<Say voice="alice">No response received. Goodbye.</Say>'
        );
    }

    /** Voice DTMF handler: "1" acknowledges the incident on behalf of the called DO. */
    public function voiceGather(Request $request, string $secret): Response
    {
        $this->assertSecret($secret);

        if ((string) $request->input('Digits') !== '1') {
            return $this->twiml('<Say voice="alice">No acknowledgement received. Goodbye.</Say>');
        }

        $incident = Incident::find($request->query('incident'));
        $do = User::find($request->query('user'));

        if (!$do) {
            // Never acknowledge on behalf of nobody: with a null user the incident would
            // be marked managed with no controller and escalation would silently stop.
            return $this->twiml(
                '<Say voice="alice">We could not match this call to a duty officer, so the incident '
                .'has not been acknowledged. Goodbye.</Say>'
            );
        }

        if ($incident && !$incident->incident_controller_id) {
            $this->incidentService->acknowledge($incident, $do, 'a voice call');
        }

        return $this->twiml(
            '<Say voice="alice">Thank you. You have acknowledged the incident and are now the incident '
            .'controller. Please open Subterra to coordinate the response. Goodbye.</Say>'
        );
    }

    /** Voice TwiML for a self-test call (just confirms the phone rings + reads a message). */
    public function voiceTest(Request $request, string $secret): Response
    {
        $this->assertSecret($secret);

        return $this->twiml(
            '<Say voice="alice">This is a test call from Subterra. Your phone is correctly configured '
            .'to receive emergency callout alerts. Goodbye.</Say>'
        );
    }

    private function handleAck(string $normalizedPhone): Response
    {
        $do = User::whereHas('roles', fn ($q) => $q->where('slug', 'duty_officer'))
            ->where('phone', 'like', "%{$normalizedPhone}")
            ->first();

        $incident = Incident::where('status', 'open')->doesntHave('controller')->latest()->first();

        if (!$incident) {
            return $this->twiml('<Message>There is no open incident to acknowledge.</Message>');
        }

        if (!$do) {
            return $this->twiml('<Message>We could not match your number to a duty officer.</Message>');
        }

        $this->incidentService->acknowledge($incident, $do, 'an SMS reply');

        return $this->twiml('<Message>Thank you '.$do->name.'. You are now the incident controller. Open Subterra to coordinate.</Message>');
    }

    private function findCalloutByPhone(string $normalizedPhone): ?Callout
    {
        return Callout::query()
            ->whereIn('status', ['active', 'triggered'])
            ->where(function ($query) use ($normalizedPhone) {
                $query->whereHas('participants', function ($q) use ($normalizedPhone) {
                    $q->where('phone', 'like', "%{$normalizedPhone}")
                        ->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$normalizedPhone}"));
                })->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$normalizedPhone}"));
            })
            ->first();
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function assertSecret(string $secret): void
    {
        $expected = (string) config('services.twilio.webhook_secret');

        if ($expected === '' || !hash_equals($expected, $secret)) {
            abort(403, 'Invalid webhook secret.');
        }
    }

    private function twiml(string $inner): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Response>'.$inner.'</Response>';

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
