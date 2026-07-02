<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\PermitResource;
use App\Mail\BookingApprovedMail;
use App\Mail\BookingSubmittedMail;
use App\Models\Booking;
use App\Models\Cave;
use App\Models\Permit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    use AuthorizesRequests;

    /**
     * List all active permits (public).
     */
    public function publicPermits(): ResourceCollection
    {
        $permits = Permit::with(['caves.system', 'officers'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return PermitResource::collection($permits);
    }

    /**
     * Public detail view for a single active permit (by slug).
     */
    public function showPermit(Permit $permit): JsonResponse
    {
        abort_unless($permit->is_active, 404);

        $permit->load(['caves.system', 'officers']);

        return response()->json(['data' => new PermitResource($permit)]);
    }

    /**
     * Get the permit info for a cave (if any).
     */
    public function permitForCave(Cave $cave): JsonResponse
    {
        $permit = $cave->permit()->with('caves')->where('is_active', true)->first();

        if (!$permit) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new PermitResource($permit)]);
    }

    /**
     * Get approved bookings for a permit (calendar data).
     */
    public function calendarForPermit(Request $request, Permit $permit): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        return response()->json([
            'data' => $this->buildCalendarData($permit, $request->input('month')),
            'permit' => [
                'has_max_groups_per_day' => $permit->has_max_groups_per_day,
                'max_groups_per_day' => $permit->max_groups_per_day,
                'has_season' => $permit->has_season,
                'season_start' => $permit->season_start,
                'season_end' => $permit->season_end,
            ],
        ]);
    }

    /**
     * Public, unauthenticated calendar data for embedding a permit's
     * availability on an external website (iframe). Returns booking counts and
     * availability only — no personal data — plus the display fields the embed
     * needs for its header and "book on Subterra" call to action.
     */
    public function embedCalendar(Request $request, Permit $permit): JsonResponse
    {
        abort_unless($permit->is_active, 404);

        $validator = Validator::make($request->all(), [
            'month' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $permit->loadMissing('caves');

        return response()->json([
            'data' => $this->buildCalendarData($permit, $request->input('month')),
            'permit' => [
                'name' => $permit->name,
                'slug' => $permit->slug,
                'caves' => $permit->caves->map(fn (Cave $cave) => ['name' => $cave->name])->values(),
                'has_max_groups_per_day' => $permit->has_max_groups_per_day,
                'max_groups_per_day' => $permit->max_groups_per_day,
                'has_season' => $permit->has_season,
                'season_start' => $permit->season_start,
                'season_end' => $permit->season_end,
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Build the per-day booking counts and availability map for a permit over a
     * single month (keyed by Y-m-d).
     *
     * Approved bookings determine availability; pending ones are surfaced so
     * applicants can see a day may fill once outstanding applications are reviewed.
     *
     * @return array<string, array{booking_count: int, pending_count: int, available: bool}>
     */
    private function buildCalendarData(Permit $permit, string $month): array
    {
        $startDate = $month.'-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $bookings = $permit->bookings()
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->select('date', 'status')
            ->selectRaw('count(*) as booking_count')
            ->groupBy('date', 'status')
            ->get();

        $calendarData = [];
        foreach ($bookings as $item) {
            $key = $item->date->toDateString();
            $calendarData[$key] ??= ['booking_count' => 0, 'pending_count' => 0, 'available' => true];

            if ($item->status === 'approved') {
                $calendarData[$key]['booking_count'] = $item->booking_count;
            } else {
                $calendarData[$key]['pending_count'] = $item->booking_count;
            }
        }

        foreach ($calendarData as &$day) {
            $day['available'] = !$permit->has_max_groups_per_day || $day['booking_count'] < $permit->max_groups_per_day;
        }
        unset($day);

        return $calendarData;
    }

    /**
     * Submit a booking application.
     */
    public function store(Request $request, Permit $permit): JsonResponse
    {
        if (!$permit->is_active) {
            return response()->json(['error' => 'This permit is not currently accepting bookings.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'participants' => [
                'required',
                'integer',
                'min:1',
                $permit->has_max_participants ? 'max:'.$permit->max_participants : null,
            ],
            'notes' => 'nullable|string|max:1000',
            'conditions_accepted' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $date = $request->input('date');

        return DB::transaction(function () use ($request, $permit, $date) {
            // Lock the permit row to prevent concurrent bookings from overbooking
            $permit = Permit::lockForUpdate()->find($permit->id);

            if (!$permit->isDateAvailable($date)) {
                return response()->json(['error' => 'This date is fully booked.'], 422);
            }

            if (!$permit->isInSeason($date)) {
                return response()->json(['error' => 'This date is outside the permit season.'], 422);
            }

            // Guard against double-submits: one live application per user/date.
            $alreadyBooked = $permit->bookings()
                ->where('user_id', $request->user()->id)
                ->whereDate('date', $date)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($alreadyBooked) {
                return response()->json(['error' => 'You already have a booking for this date.'], 422);
            }

            $booking = Booking::create([
                'permit_id' => $permit->id,
                'user_id' => $request->user()->id,
                'date' => $date,
                'participants' => $request->input('participants'),
                'notes' => $request->input('notes'),
                'status' => $permit->auto_approve ? 'approved' : 'pending',
                'approved_at' => $permit->auto_approve ? now() : null,
                'conditions_accepted_at' => now(),
            ]);

            $booking->load(['permit', 'applicant']);

            // Notify officers
            $officers = $permit->officers;
            foreach ($officers as $officer) {
                Mail::to($officer->email)->queue(
                    new BookingSubmittedMail($booking, $officer)
                );
            }

            // If auto-approved, also send approval email to applicant
            if ($permit->auto_approve) {
                Mail::to($booking->applicant->email)->queue(
                    new BookingApprovedMail($booking)
                );
            }

            return response()->json(new BookingResource($booking), 201);
        });
    }

    /**
     * Get the current user's bookings.
     */
    public function mine(Request $request): ResourceCollection
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['permit.officers'])
            ->orderBy('date', 'desc')
            ->get();

        return BookingResource::collection($bookings);
    }

    /**
     * Cancel a booking.
     */
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        $booking->update(['status' => 'cancelled']);

        return response()->json(new BookingResource($booking->load('permit')));
    }
}
