<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Http\Resources\UserDetailEmailResource;
use App\Http\Resources\UserDetailResource;
use App\Http\Resources\UserResource;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(): ResourceCollection
    {
        $currentUser = auth()->user();

        // Get IDs of users in the same clubs
        $clubUserIds = collect();
        if ($currentUser) {
            $clubUserIds = $currentUser->clubs()
            ->with('users:id')
            ->get()
            ->pluck('users')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->filter(fn($id) => $id !== $currentUser->id); // Remove self if present
        }

        // Count how many trips each user has shared with the current user
        $trips = \App\Models\Trip::whereHas('participants', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })
            ->with('participants:id')
            ->get();

        $tripUserCounts = collect();
        foreach ($trips as $trip) {
            foreach ($trip->participants as $participant) {
                if ($participant->id !== $currentUser->id) {
                    $tripUserCounts[$participant->id] = ($tripUserCounts[$participant->id] ?? 0) + 1;
                }
            }
        }
        

        // Score users: +2 for each shared trip, +1 for same club
        $users = User::all()->map(function ($user) use ($clubUserIds, $tripUserCounts, $currentUser) {
            $score = 0;
            if ($clubUserIds->contains($user->id)) {
                $score += 1;
            }
            if (isset($tripUserCounts[$user->id])) {
                $score += 2 * $tripUserCounts[$user->id];
            }
            $user->proximity_score = $score;
            return $user;
        })->sortByDesc('proximity_score')->values();

        return UserResource::collection($users);
    }

    /**
     * Admin endpoint to get all users with detailed info.
     */
    public function adminIndex(): ResourceCollection
    {
        // Need withoutGlobalScopes here as withoutScopedBindings() doesn't affect this query
        return UserDetailEmailResource::collection(User::withoutGlobalScopes()->get());
    }

    /**
     * Toggle the approval status of a user.
     */
    public function toggleApproval(User $user): UserDetailEmailResource
    {
        // Route model binding handles fetching the user without scopes via withoutScopedBindings()
        $user->is_approved = !$user->is_approved;
        $user->save();
        return new UserDetailEmailResource($user);
    }

    /**
     * Toggle the admin status of a user.
     */
    public function toggleAdmin(User $user): UserDetailEmailResource
    {
        $user->is_admin = !$user->is_admin;
        $user->save();
        return new UserDetailEmailResource($user);
    }

    /**
     * Create a new user (potentially needs more robust implementation).
     */
    public function create(Request $request): UserDetailEmailResource
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            // Add other fields as necessary
        ]);

        // Determine default photo path correctly based on storage setup
        $defaultPhotoPath = 'profile/default.webp'; // Adjust if needed

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'is_active' => false,
            'is_approved' => false,
            'is_admin' => false,
            'photo' => $defaultPhotoPath, 
        ]);

        return new UserDetailEmailResource($user);
    }

    public function show(User $user): UserDetailResource
    {
        return new UserDetailResource($user);
    }

    /**
     * Update user profile information (bio, name, club).
     */
    public function store(User $user, Request $request): UserDetailEmailResource
    {
        $validatedData = $request->validate([
            'bio' => 'nullable|string',
            'name' => 'nullable|string|max:255',
            // Add validation for other editable fields if needed
        ]);

        $user->update($validatedData);
        return new UserDetailEmailResource($user);
    }

    /**
     * Get the 10 most recent trips for a user.
     */
    public function recentTrips(User $user): ResourceCollection
    {
        $trips = Trip::whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('start_time', '>=', Carbon::now()->subYear())
            ->orderBy('start_time', 'desc')
            ->limit(10)
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * Get activity heatmap data for a user (trips per day in the last year).
     */
    public function activityHeatmap(User $user): JsonResponse
    {
        $oneYearAgo = Carbon::now()->subYear();

        $activity = Trip::select(DB::raw('DATE(start_time) as date'), DB::raw('count(*) as count'))
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('start_time', '>=', $oneYearAgo)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                // Format for vue3-calendar-heatmap: { 'YYYY-MM-DD': count }
                return ['date' => $item->date, 'count' => $item->count];
            });

        return response()->json($activity);
    }

    /**
     * List all medals accomplished by the user.
     */
    public function medals(User $user): JsonResponse
    {
        $medals = $user->medals()->get();
        return response()->json([
            'user_id' => $user->id,
            'medals' => $medals
        ]);
    }

    /**
     * Delete a user account. Deletes any trips where the user was the only participant.
     * Keeps all other trips (removes user from them).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Only allow the user themselves or an admin to delete
        if ($request->user()->id !== $user->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Find all trips where this user is a participant
        $trips = $user->trips()->get();
        foreach ($trips as $trip) {
            $participantCount = $trip->participants()->count();
            if ($participantCount === 1) {
                // User is the only participant, delete the trip
                $trip->delete();
            } else {
                // Remove user from trip participants
                $trip->participants()->detach($user->id);
            }
        }

        // Remove user from any clubs (detach from pivot)
        $user->clubs()->detach();

        // Delete the user
        $user->delete();

        return response()->json(['message' => 'Account deleted.'], 200);
    }
}
