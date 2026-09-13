<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cave;
use App\Models\CaveMedia;
use App\Models\Report;
use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Spatie\SlackAlerts\Facades\SlackAlert;

class ReportController extends Controller
{
    /**
     * The things a member is allowed to report, mapped from the short name the
     * client sends to the model class. An allowlist rather than a free-form
     * class string: accepting the latter would let a caller point a report at
     * any model in the application.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private const REPORTABLE_TYPES = [
        'trip' => Trip::class,
        'user' => User::class,
        'cave' => Cave::class,
        'trip_media' => TripMedia::class,
        'cave_media' => CaveMedia::class,
    ];

    /**
     * Raise a report about content or a member.
     *
     * Deliberately forgiving: the reporter picks a category and may add detail,
     * and nothing else is required. A reporting flow that interrogates the
     * person raising a concern is a reporting flow people abandon.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reportable_type' => ['required', 'string', Rule::in(array_keys(self::REPORTABLE_TYPES))],
            'reportable_id' => ['required', 'string'],
            'category' => ['required', 'string', Rule::in(Report::USER_SELECTABLE_CATEGORIES)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $class = self::REPORTABLE_TYPES[$validated['reportable_type']];

        $target = $this->resolveTarget($class, $validated['reportable_id']);

        if ($target === null) {
            return response()->json(['message' => 'The reported item could not be found.'], 404);
        }

        // Store the primary key, so the morphTo relation resolves, even though the
        // client addressed the target by its public identifier.
        $targetKey = (string) $target->getKey();

        $reporter = $request->user();

        // One open report per person per target. A second submission is treated
        // as success rather than an error: the reporter's concern is already
        // recorded, and telling them off for double-checking helps nobody.
        $existing = Report::where('reporter_id', $reporter->id)
            ->where('reportable_type', $class)
            ->where('reportable_id', $targetKey)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reported this. Our moderators are looking at it.',
                'data' => ['id' => $existing->id],
            ], 200);
        }

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'reportable_type' => $class,
            'reportable_id' => $targetKey,
            'category' => $validated['category'],
            'details' => $validated['details'] ?? null,
            'status' => 'open',
        ]);

        $this->alertModerators($report);

        return response()->json([
            'message' => 'Thanks — this has been sent to our moderators.',
            'data' => ['id' => $report->id],
        ], 201);
    }

    /**
     * Resolve the reported thing from the identifier the client actually has.
     *
     * The API publishes models by their route key, not their primary key — a
     * trip is addressed by its short_id, a cave by its slug — so that is what
     * the client sends back here. Looking the value up by primary key instead
     * would put a short_id into a query against a bigint column, which SQLite
     * silently coerces and PostgreSQL rejects outright.
     */
    private function resolveTarget(string $class, string $identifier): ?\Illuminate\Database\Eloquent\Model
    {
        $routeKey = (new $class())->getRouteKeyName();

        // Users carry a global active scope that would hide deactivated accounts —
        // a report about one of those is exactly the sort that matters.
        $query = $class === User::class
            ? User::withoutGlobalScopes()
            : $class::query();

        return $query->where($routeKey, $identifier)->first();
    }

    /**
     * Push urgent reports at the moderators rather than waiting for someone to
     * open the admin panel. A child-safety report sitting unseen for a week is
     * the failure mode this whole feature exists to prevent.
     */
    private function alertModerators(Report $report): void
    {
        if (!$report->isUrgent()) {
            return;
        }

        try {
            SlackAlert::to('signups')->message(
                "🚩 *{$report->category}* report raised on {$report->reportable_type} #{$report->reportable_id}"
                .' — <https://subterra.world/admin/reports|review in admin>'
            );
        } catch (\Exception $e) {
            // A failed alert must never fail the report itself: the record is
            // already saved and visible in the queue.
            Log::error('Failed to send report Slack alert: '.$e->getMessage());
        }
    }
}
