<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Mail\BookingApprovedMail;
use App\Mail\BookingRejectedMail;
use App\Models\Booking;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): ResourceCollection
    {
        $query = Booking::with(['permit.caves', 'applicant.clubs']);

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

        $booking->load(['permit.caves', 'applicant.clubs']);

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

        $booking->load(['permit.caves', 'applicant.clubs']);

        Mail::to($booking->applicant->email)->queue(
            new BookingRejectedMail($booking)
        );

        return response()->json(new BookingResource($booking));
    }
}
