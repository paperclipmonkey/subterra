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
    public function index(Request $request): ResourceCollection
    {
        $currentUser = auth()->user();
        $search = $request->input('search');

        // If no search term or too short, return empty list
        if (!$search || strlen($search) < 3) {
            return UserResource::collection(collect());
        }

        // Get IDs of users in the same clubs
        $clubUserIds = collect();
        if ($currentUser) {
            $clubUserIds = $currentUser->clubs()
            ->with('users:id')
            ->get()
            ->pluck('users')
            ->flatten()
            ->pluck('id')
            ->unique() // Unique is sufficient, no need to filter distinct
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
        
        
        // Find users matching search term (Name or Email)
        // LIKE is case-insensitive in MySQL by default
        // But email exact match needs case-insensitive comparison with LOWER
        $users = User::where(function($query) use ($search) {
                $query->where('name', 'LIKE', '%' . $search . '%')
                      ->orWhereRaw('LOWER(email) = ?', [strtolower($search)]);
            })
            ->get()
        ->filter(function ($user) use ($currentUser, $clubUserIds, $tripUserCounts, $search) {
            // Always allow self
            if ($currentUser && $user->id === $currentUser->id) {
                return true;
            }

            // Allow exact email match regardless of other rules (if they know the email, they can add)
            if (strcasecmp($user->email, $search) === 0) {
                 return true;
            }

            // Public users are visible
            if ($user->visibility_addable === 'public') {
                return true;
            }

            // Users in shared clubs are visible (regardless of their visibility_addable setting)
            if ($clubUserIds->contains($user->id)) {
                return true;
            }

            // Users with shared trip history are visible
            if (isset($tripUserCounts[$user->id])) {
                return true; 
            }

            // If none of the above, hide them
            return false; 

        })->map(function ($user) use ($clubUserIds, $tripUserCounts) {
            $score = 0;
            // Priority 1 (High): Previous Trips
            if (isset($tripUserCounts[$user->id])) {
                // Give a high score for trips, potentially weighted by count
                $score += 10 + ($tripUserCounts[$user->id]); 
            }
            
            // Priority 2: Clubs in common
            if ($clubUserIds->contains($user->id)) {
                $score += 5;
            }
            
            // Priority 3: Public (Base visibility)
            if ($user->visibility_addable === 'public') {
                $score += 1;
            }

            $user->proximity_score = $score;
            return $user;
        })
        ->sortByDesc('proximity_score')
        ->values();

        return UserResource::collection($users);
    }

    /**
     * Admin endpoint to get all users with detailed info.
     */
    public function adminIndex(): ResourceCollection
    {
        // Need withoutGlobalScopes here as withoutScopedBindings() doesn't affect this query
        // Only show active users (actual signups)
        return UserDetailEmailResource::collection(User::withoutGlobalScopes()->where('is_active', true)->with('roles')->get());
    }


    /**
     * Toggle the admin status of a user.
     */
    public function toggleAdmin(User $user): UserDetailEmailResource
    {
        if ($user->hasRole('platform_admin')) {
            $user->removeRole('platform_admin');
        } else {
            $user->assignRole('platform_admin');
        }
        
        return new UserDetailEmailResource($user);
    }

    /**
     * Toggle a specific role for a user.
     */
    public function toggleRole(User $user, string $role): UserDetailEmailResource
    {
        // Prevent users from editing their own roles
        if ($user->id === auth()->id()) {
            abort(403, 'You cannot modify your own roles.');
        }

        // Validate the role slug exists
        $roleModel = \App\Models\Role::where('slug', $role)->firstOrFail();

        if ($user->hasRole($role)) {
            $user->removeRole($role);
        } else {
            $user->assignRole($role);
        }
        
        // Reload roles relationship
        $user->load('roles');
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
        // $defaultPhotoPath = 'profile/default.webp'; // Adjust if needed

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'photo' => null, 
        ]);
        
        // Explicitly set guarded fields (not mass assignable)
        $user->is_active = false;
        $user->save();

        event(new \App\Events\UserCreated($user));

        return new UserDetailEmailResource($user);
    }

    public function show($id): UserDetailResource
    {
        $user = User::withoutGlobalScopes()
            ->with(['trips.system', 'clubs', 'medals'])
            ->findOrFail($id);

        return new UserDetailResource($user);
    }

    /**
     * Update user profile information (bio, name, club).
     */
    public function store(User $user, Request $request): UserDetailEmailResource
    {
        $validatedData = $request->validate([
            'bio' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^(07[0-9]{9}|\+44[0-9]{10})$/', 'unique:users,phone,' . $user->id],
            'email_trophies' => ['nullable', 'boolean'],
            'email_tagged' => ['nullable', 'boolean'],
            'email_platform_news' => ['nullable', 'boolean'],
            'visibility_addable' => ['nullable', 'string', 'in:public,club'],
        ]);

        $user->update($validatedData);
        return new UserDetailEmailResource($user);
    }

    /**
     * Get the 10 most recent trips for a user.
     */
    public function recentTrips($id): ResourceCollection
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);

        $trips = Trip::whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('start_time', '>=', Carbon::now()->subYear())
            ->with('entrance', 'participants')
            ->orderBy('start_time', 'desc')
            ->limit(10)
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * Get activity heatmap data for a user (trips per day in the last year).
     */
    /**
     * Get activity heatmap data for a user (trips per day in the last year).
     */
    public function activityHeatmap($id): JsonResponse
    {
        $user = User::withoutGlobalScopes()->findOrFail($id);
        
        $oneYearAgo = Carbon::now()->subYear();

        $activity = Trip::whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('start_time', '>=', $oneYearAgo)
            ->whereNotNull('end_time') // Ensure we have an end time
            ->get()
            ->groupBy(function ($trip) {
                return $trip->start_time->format('Y-m-d');
            })
            ->map(function ($tripsOnDate, $date) {
                $totalMinutes = $tripsOnDate->sum(function ($trip) {
                    return $trip->start_time->diffInMinutes($trip->end_time);
                });
                
                return [
                    'date' => $date,
                    'count' => round($totalMinutes / 60, 1)
                ];
            })
            ->sortBy('date')
            ->values();

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

    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'bio' => $user->bio,
                'is_active' => $user->is_active,
                'is_active' => $user->is_active,
                'tos_agreed_at' => $user->tos_agreed_at,
                'created_at' => $user->created_at,
            ],
            'clubs' => $user->clubs->map(fn($club) => [
                'name' => $club->name,
                'status' => $club->pivot->status,
                'is_admin' => $club->pivot->is_admin,
            ]),
            'medals' => $user->medals->map(fn($medal) => [
                'name' => $medal->name,
                'description' => $medal->description,
                'awarded_at' => $medal->pivot->awarded_at,
            ]),
            'trips' => $user->trips->map(fn($trip) => [
                'id' => $trip->id,
                'start_time' => $trip->start_time,
                'end_time' => $trip->end_time,
                'description' => $trip->description,
                'visibility' => $trip->visibility,
            ]),
            'callouts' => $user->callouts->map(fn($callout) => [
                'id' => $callout->id,
                'callout_time' => $callout->callout_time,
                'description' => $callout->description,
                'status' => $callout->status,
                'car_registration' => $callout->car_registration,
            ]),
        ];

        $filename = 'subterra_data_export_' . now()->format('Y-m-d') . '.json';

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Delete a user account. Deletes any trips where the user was the only participant.
     * Keeps all other trips (removes user from them).
     */
    public function destroy(Request $request, User $user_without_scopes): JsonResponse
    {
        $user = $user_without_scopes;
        // Only allow the user themselves or an admin to delete
        if ($request->user()->id !== $user->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 1. Delete user photo if it's not the default
        if ($user->photo && 
            !str_contains($user->photo, 'default.webp') && 
            !str_contains($user->photo, 'default.png')
        ) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->photo);
        }

        // 2. Clear trips
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

        // 3. Detach from clubs and medals
        $user->clubs()->detach();
        $user->medals()->detach();

        // 4. Delete the user
        // related callouts, incidents, onto_call_shifts, and collections 
        // will be deleted via database cascades.
        // incident_notes will be set to null via database cascade (set null).
        $user->delete();

        return response()->json(['message' => 'Account deleted.'], 200);
    }

    public function updateMe(Request $request): UserDetailEmailResource
    {
        $user = $request->user();
        return $this->store($user, $request);
    }

    public function destroyMe(Request $request): JsonResponse
    {
        $user = $request->user();
        return $this->destroy($request, $user);
    }
}
