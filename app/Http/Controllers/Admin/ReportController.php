<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    /**
     * The moderation queue.
     *
     * Ordered so the work that matters surfaces first: open before resolved,
     * urgent categories before the rest, then newest first.
     */
    public function index(Request $request): ResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in([...Report::STATUSES, 'all'])],
            'category' => ['nullable', 'string', Rule::in(Report::CATEGORIES)],
        ]);

        $status = $validated['status'] ?? 'open';

        $query = Report::with(['reporter', 'handler', 'reportable' => function ($morphTo) {
            // Users carry a global active scope, and the objection flow deactivates
            // the account it is about — without this every objection would reach the
            // moderator labelled "(deleted)" with no way to see who it concerns.
            $morphTo->constrain([
                User::class => fn ($q) => $q->withoutGlobalScopes(),
            ]);
        }])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($validated['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN category IN (?, ?) THEN 0 ELSE 1 END', Report::URGENT_CATEGORIES)
            ->orderByDesc('created_at');

        return ReportResource::collection($query->paginate(50));
    }

    /** Counts for the admin dashboard badge, so an unopened queue is still visible. */
    public function counts(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'open' => Report::where('status', 'open')->count(),
            'urgent_open' => Report::where('status', 'open')
                ->whereIn('category', Report::URGENT_CATEGORIES)
                ->count(),
        ]);
    }

    /**
     * Resolve a report.
     *
     * Resolving records who decided and what they decided; it never deletes the
     * report. Reopening is allowed — a complaint dismissed in haste has to be
     * recoverable.
     */
    public function update(Request $request, Report $report): ReportResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Report::STATUSES)],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $isResolved = $validated['status'] !== 'open';

        $report->update([
            'status' => $validated['status'],
            'resolution_note' => $validated['resolution_note'] ?? $report->resolution_note,
            'handled_by' => $isResolved ? $request->user()->id : null,
            'handled_at' => $isResolved ? now() : null,
        ]);

        return new ReportResource($report->fresh(['reporter', 'handler', 'reportable']));
    }
}
