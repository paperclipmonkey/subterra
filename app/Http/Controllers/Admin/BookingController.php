<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Mail\BookingApprovedMail;
use App\Mail\BookingMessageMail;
use App\Mail\BookingRejectedMail;
use App\Models\Booking;
use App\Models\Permit;
use App\Models\User;
use App\Services\BookingSlackNotifier;
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

    public function index(Request $request): ResourceCollection
    {
        $query = Booking::with([...array_map(fn ($r) => "permit.$r", Permit::RESOURCE_RELATIONS), 'applicant.clubs']);

        // Access officers only see bookings for permits they administer.
        if (!$request->user()->hasRole('platform_admin')) {
            $query->whereHas('permit.officers', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        if ($request->has('permit_id')) {
            $query->where('permit_id', $request->input('permit_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->input('date_to'));
        }

        $bookings = $query->orderBy('date')->get();

        return BookingResource::collection($bookings);
    }

    public function approve(Request $request, Booking $booking, BookingSlackNotifier $slack): JsonResponse
    {
        $this->authorize('approve', $booking);

        if ($booking->status !== 'pending') {
            return response()->json(['error' => 'Only pending bookings can be approved.'], 422);
        }

        $response = DB::transaction(function () use ($request, $booking) {
            // Lock the permit row (same protocol as public booking creation) so
            // concurrent approvals can't exceed the day's capacity.
            $permit = Permit::lockForUpdate()->find($booking->permit_id);

            $date = $booking->date->toDateString();

            if (!$request->boolean('override_limit') && !$permit->isDateAvailable($date)) {
                return $this->overLimitResponse($permit, $date);
            }

            $booking->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            return;
        });

        if ($response !== null) {
            return $response;
        }

        $booking->load(['permit.caves', 'applicant.clubs']);

        Mail::to($booking->applicant->email)->queue(
            new BookingApprovedMail($booking)
        );

        $slack->approved($booking, $request->user());

        return response()->json(new BookingResource($booking));
    }

    public function reject(Request $request, Booking $booking, BookingSlackNotifier $slack): JsonResponse
    {
        $this->authorize('reject', $booking);

        if ($booking->status !== 'pending') {
            return response()->json(['error' => 'Only pending bookings can be rejected.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $booking->update([
            'status' => 'rejected',
            'rejection_reason' => $validator->validated()['rejection_reason'],
        ]);

        $booking->load(['permit.caves', 'applicant.clubs']);

        Mail::to($booking->applicant->email)->queue(
            new BookingRejectedMail($booking)
        );

        $slack->rejected($booking, $request->user());

        return response()->json(new BookingResource($booking));
    }

    public function adminStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permit_slug' => 'required|string|exists:permits,slug',
            'user_id' => 'nullable|string|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'participants' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $permit = Permit::where('slug', $data['permit_slug'])->firstOrFail();

        if (!$request->user()->hasRole('platform_admin')
            && !$permit->officers()->where('user_id', $request->user()->id)->exists()) {
            abort(403);
        }

        $applicant = isset($data['user_id']) ? User::findOrFail($data['user_id']) : null;

        $result = DB::transaction(function () use ($request, $permit, $data, $applicant) {
            // Lock the permit row (same protocol as public booking creation) so
            // this insert can't race a concurrent booking past the daily limit.
            $permit = Permit::lockForUpdate()->find($permit->id);

            if (!$request->boolean('override_limit') && !$permit->isDateAvailable($data['date'])) {
                return $this->overLimitResponse($permit, $data['date']);
            }

            if (!$permit->isInSeason($data['date'])) {
                return response()->json(['error' => 'This date is outside the permit season.'], 422);
            }

            return Booking::create([
                'permit_id' => $permit->id,
                'user_id' => $applicant?->id,
                'date' => $data['date'],
                'participants' => $data['participants'],
                'notes' => $data['notes'] ?? null,
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'conditions_accepted_at' => now(),
            ]);
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $booking = $result;
        $booking->load(['permit.caves', 'applicant.clubs']);

        if ($applicant) {
            Mail::to($applicant->email)->queue(new BookingApprovedMail($booking));
        }

        return response()->json(new BookingResource($booking), 201);
    }

    /**
     * The daily limit is soft for officers: they're told the day is full and
     * can resend with override_limit to go over it.
     */
    private function overLimitResponse(Permit $permit, string $date): JsonResponse
    {
        $approved = $permit->approvedBookingsForDate($date)->count();

        return response()->json([
            'error' => "This date already has {$approved} approved ".($approved === 1 ? 'group' : 'groups')
                ." (daily limit {$permit->max_groups_per_day}).",
            'over_limit' => true,
        ], 422);
    }

    public function message(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('approve', $booking);

        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $booking->load(['permit', 'applicant']);

        Mail::to($booking->applicant->email)->queue(
            new BookingMessageMail($booking, $validator->validated()['message'], $request->user())
        );

        return response()->json(['message' => 'Message sent successfully.']);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('cancel', $booking);

        if (!in_array($booking->status, ['pending', 'approved'])) {
            return response()->json(['error' => 'Only pending or approved bookings can be cancelled.'], 422);
        }

        $booking->update(['status' => 'cancelled']);
        $booking->load(['permit.caves', 'applicant.clubs']);

        return response()->json(new BookingResource($booking));
    }
}
