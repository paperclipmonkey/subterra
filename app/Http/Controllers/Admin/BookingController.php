<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesBookingParticipants;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Mail\BookingApprovedMail;
use App\Mail\BookingMessageMail;
use App\Mail\BookingRejectedMail;
use App\Models\Booking;
use App\Models\Permit;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    use AuthorizesRequests;
    use ResolvesBookingParticipants;

    public function index(Request $request): ResourceCollection
    {
        $query = Booking::with(['permit.caves', 'applicant.clubs', 'participantDetails']);

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

    public function approve(Request $request, Booking $booking): JsonResponse
    {
        $this->authorize('approve', $booking);

        if ($booking->status !== 'pending') {
            return response()->json(['error' => 'Only pending bookings can be approved.'], 422);
        }

        $booking->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $booking->load(['permit.caves', 'applicant.clubs', 'participantDetails']);

        Mail::to($booking->applicant->email)->queue(
            new BookingApprovedMail($booking)
        );

        return response()->json(new BookingResource($booking));
    }

    public function reject(Request $request, Booking $booking): JsonResponse
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

        $booking->load(['permit.caves', 'applicant.clubs', 'participantDetails']);

        Mail::to($booking->applicant->email)->queue(
            new BookingRejectedMail($booking)
        );

        return response()->json(new BookingResource($booking));
    }

    public function adminStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permit_slug' => 'required|string|exists:permits,slug',
            'user_id' => 'nullable|string|exists:users,id',
            'date' => 'required|date',
            // For BCA permits the headcount comes from the named roster, so a bare
            // count isn't required when participants_detail is supplied.
            'participants' => 'required_without:participants_detail|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $data = $validator->validated();
        $permit = Permit::where('slug', $data['permit_slug'])->firstOrFail();
        $applicant = isset($data['user_id']) ? User::findOrFail($data['user_id']) : null;

        // Throws ValidationException (422) if the roster is incomplete.
        $participantRows = $permit->requires_bca
            ? $this->resolveBcaParticipants($request->all(), $permit)
            : [];

        $participantCount = $permit->requires_bca
            ? count($participantRows)
            : (int) $data['participants'];

        $booking = Booking::create([
            'permit_id' => $permit->id,
            'user_id' => $applicant?->id,
            'date' => $data['date'],
            'participants' => $participantCount,
            'notes' => $data['notes'] ?? null,
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'conditions_accepted_at' => now(),
        ]);

        if ($participantRows) {
            $booking->participantDetails()->createMany($participantRows);
        }

        $booking->load(['permit.caves', 'applicant.clubs', 'participantDetails']);

        if ($applicant) {
            Mail::to($applicant->email)->queue(new BookingApprovedMail($booking));
        }

        return response()->json(new BookingResource($booking), 201);
    }

    public function message(Request $request, Booking $booking): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $booking->load(['permit', 'applicant']);

        Mail::to($booking->applicant->email)->queue(
            new BookingMessageMail($booking, $validator->validated()['message'], $request->user()->name)
        );

        return response()->json(['message' => 'Message sent successfully.']);
    }

    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        if (!in_array($booking->status, ['pending', 'approved'])) {
            return response()->json(['error' => 'Only pending or approved bookings can be cancelled.'], 422);
        }

        $booking->update(['status' => 'cancelled']);
        $booking->load(['permit.caves', 'applicant.clubs', 'participantDetails']);

        return response()->json(new BookingResource($booking));
    }
}
