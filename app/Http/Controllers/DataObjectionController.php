<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Scopes\IsActiveScope;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

/**
 * The objection route from the Article 14 notice email.
 *
 * Reached from a signed link by someone who has never logged in and may not
 * want to, so it is a plain server-rendered page rather than part of the SPA.
 */
class DataObjectionController extends Controller
{
    public function show(Request $request, string $user): View
    {
        $model = $this->resolve($request, $user);

        return view('data-objection', [
            'user' => $model,
            'alreadyRaised' => $this->openObjectionFor($model) !== null,
        ]);
    }

    /**
     * Record the objection.
     *
     * It deliberately does not delete the account here. A signed link in an
     * inbox is decent evidence but not proof of identity, and a self-service
     * delete would take the person's trip history with it. Instead the record is
     * made invisible immediately and a moderator completes the erasure — which
     * is also what gives the platform an auditable record that the request was
     * made and honoured.
     */
    public function store(Request $request, string $user): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
            'is_minor' => ['nullable', 'boolean'],
        ]);

        $model = $this->resolve($request, $user);

        if ($this->openObjectionFor($model) === null) {
            $note = $validated['reason'] ?? null;

            if ($request->boolean('is_minor')) {
                $note = trim("Reported as an under-18 account.\n\n".(string) $note);
            }

            $report = Report::create([
                'reporter_id' => null,
                'reportable_type' => User::class,
                'reportable_id' => (string) $model->id,
                'category' => $request->boolean('is_minor') ? 'child_safety' : 'data_objection',
                'details' => $note,
                'status' => 'open',
            ]);

            // Hide the record straight away. The moderator still has to act, but
            // the person stops being findable and addable in the meantime.
            $model->forceFill([
                'is_active' => false,
                'visibility_addable' => 'club',
            ])->save();

            $this->alertModerators($report, $model);
        }

        return redirect()->route('data-objection', [
            'user' => $model->id,
            'signature' => $request->query('signature'),
            'expires' => $request->query('expires'),
        ])->with('objection_recorded', true);
    }

    /**
     * Resolve the subject without the active scope — the whole point is that
     * this person is an inactive placeholder — and only for a valid signature.
     */
    private function resolve(Request $request, string $user): User
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'This link is invalid or has expired. Please contact us directly.');
        }

        return User::withoutGlobalScope(IsActiveScope::class)->findOrFail($user);
    }

    private function openObjectionFor(User $user): ?Report
    {
        return Report::where('reportable_type', User::class)
            ->where('reportable_id', (string) $user->id)
            ->whereIn('category', ['data_objection', 'child_safety'])
            ->where('status', 'open')
            ->first();
    }

    private function alertModerators(Report $report, User $user): void
    {
        try {
            SlackAlert::to('signups')->message(
                "🚩 Data objection raised for {$user->email} (report #{$report->id})"
                .' — <https://subterra.world/admin/reports|review in admin>'
            );
        } catch (\Exception $e) {
            Log::error('Failed to send objection Slack alert: '.$e->getMessage());
        }
    }
}
