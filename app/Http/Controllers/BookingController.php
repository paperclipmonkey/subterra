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
        $permits = Permit::with('caves')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return PermitResource::collection($permits);
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

        $month = $request->input('month');
        $startDate = $month.'-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $bookings = $permit->bookings()
            ->where('status', 'approved')
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->select('date')
            ->selectRaw('count(*) as booking_count')
            ->groupBy('date')
            ->get();

        $calendarData = $bookings->mapWithKeys(function ($item) use ($permit) {
            return [$item->date->toDateString() => [
                'booking_count' => $item->booking_count,
                'available' => !$permit->has_max_groups_per_day || $item->booking_count < $permit->max_groups_per_day,
            ]];
        });

        return response()->json([
            'data' => $calendarData,
            'permit' => [
                'has_max_groups_per_day' => $permit->has_max_groups_per_day,
                'max_groups_per_day' => $permit->max_groups_per_day,
            ],
        ]);
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
            'date' => 'required|date|after_or_equal:today',
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

        if (!$permit->isDateAvailable($date)) {
            return response()->json(['error' => 'This date is fully booked.'], 422);
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
    }

    /**
     * Get the current user's bookings.
     */
    public function mine(Request $request): ResourceCollection
    {
        $bookings = $request->user()
            ->bookings()
            ->with(['permit'])
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
