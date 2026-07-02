<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Callout;
use App\Services\GcpWatchdogService;
use Illuminate\Http\Request;

class CalloutController extends Controller
{
    protected GcpWatchdogService $watchdogService;

    public function __construct(GcpWatchdogService $watchdogService)
    {
        $this->watchdogService = $watchdogService;
    }

    public function index()
    {
        $callouts = Callout::with('cave', 'exitCave', 'participants', 'user')
            ->whereIn('status', ['active', 'triggered']) // Fetch both so we can show complete picture, or just active?
            // User request: "Open callouts ... as well as flagging those that have turned into active incidents"
            // If I fetch triggered here, they duplicate what IncidentController fetches.
            // But it might be cleaner to have a "Live Operations" view here.
            // Let's fetch 'active' and 'triggered'.
            ->orderBy('callout_time', 'asc')
            ->get();

        $data = $callouts->map(function ($callout) {
            $lat = null;
            $lng = null;
            if ($callout->cave) {
                $lat = $callout->cave->location_lat;
                $lng = $callout->cave->location_lng;
            } elseif (!empty($callout->location_data) && isset($callout->location_data['latitude'], $callout->location_data['longitude'])) {
                $lat = $callout->location_data['latitude'];
                $lng = $callout->location_data['longitude'];
            }

            return [
                'id' => $callout->id,
                'status' => $callout->status,
                'cave_name' => $callout->cave ? $callout->cave->name : ($callout->description ?: 'Unknown Location'),
                'exit_cave_name' => $callout->exitCave ? $callout->exitCave->name : null,
                'callout_time' => $callout->callout_time,
                'team_size' => $callout->participants->count(),
                'leader_name' => $callout->user ? $callout->user->name : 'Unknown',
                'additional_people' => max(0, $callout->participants->count() - 1),
                'route' => $callout->trip_plan,
                'has_incident' => $callout->status === 'triggered',
                'incident_id' => $callout->incident ? $callout->incident->id : null,
                'lat' => $lat,
                'lng' => $lng,
            ];
        });

        $watchdogCount = $this->watchdogService->getActiveWatchdogCount();
        $systemCount = $callouts->where('status', 'active')->count();

        return response()->json([
            'data' => $data,
            'watchdog_count' => $watchdogCount,
            'system_count' => $systemCount,
            // Negative counts are sentinels (-1 unreachable, -2 not configured), not a
            // divergence — only a real count that differs from ours is out of sync.
            'is_watchdog_out_of_sync' => $watchdogCount >= 0 && $watchdogCount !== $systemCount,
            // Live (read at request time, so it never goes stale) — surfaced as a link on the dashboard.
            'whatsapp_group_url' => config('callouts.whatsapp_group_url'),
            // The numbers alerts are sent from, so duty officers can save them as contacts.
            'contact_numbers' => [
                'primary' => config('callouts.numbers.primary_sms'),
                'backup' => config('callouts.numbers.backup_sms'),
            ],
            // Remaining SMS credit on each provider (cached). Low credit is surfaced
            // prominently because it can silently stop alerts going out.
            'sms_balances' => app(\App\Services\Sms\SmsBalanceService::class)->providerStatuses(),
        ]);
    }

    /**
     * Send a test callout to the watchdog.
     */
    public function sendTestWatchdogCallout(Request $request)
    {
        $user = $request->user();

        $success = $this->watchdogService->sendTestCallout([
            'callout_id' => 'test-'.now()->timestamp,
            'user' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
            ],
            'cave_name' => 'Test Location (Admin Panel)',
        ]);

        if ($success) {
            return response()->json(['message' => 'Test callout sent to watchdog successfully.']);
        }

        return response()->json(['message' => 'Failed to send test callout to watchdog.'], 500);
    }
}
