<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserDetailResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return UserResource::collection(User::all());
    }

    /**
     * Admin endpoint to get all users with detailed info.
     */
    public function adminIndex()
    {
        // Need withoutGlobalScopes here as withoutScopedBindings() doesn't affect this query
        return UserDetailResource::collection(User::withoutGlobalScopes()->get());
    }

    /**
     * Toggle the approval status of a user.
     */
    public function toggleApproval(User $user)
    {
        // Route model binding handles fetching the user without scopes via withoutScopedBindings()
        $user->is_approved = !$user->is_approved;
        $user->save();
        return new UserDetailResource($user);
    }

    /**
     * Toggle the admin status of a user.
     */
    public function toggleAdmin(User $user)
    {
        // Route model binding handles fetching the user without scopes via withoutScopedBindings()
        // Optional: Prevent removing the last admin
        // if ($user->is_admin && User::where('is_admin', true)->count() === 1) {
        //     return response()->json(['message' => 'Cannot remove the last admin.'], 400);
        // }
        $user->is_admin = !$user->is_admin;
        $user->save();
        return new UserDetailResource($user);
    }

    /**
     * Create a new user (potentially needs more robust implementation).
     */
    public function create(Request $request) {
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
            'is_active' => false, // Or true depending on desired default
            'is_approved' => false, // Default to not approved
            'is_admin' => false, // Default to not admin
            'photo' => $defaultPhotoPath, 
            // Add password handling if creating users this way
            // 'password' => bcrypt('default_password'), // Example
        ]);

        return new UserDetailResource($user);
    }

    public function show(User $user)
    {
        return new UserDetailResource($user);
    }

    /**
     * Update user profile information (bio, club).
     */
    public function store(User $user, Request $request) {
        $validatedData = $request->validate([
            'club' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            // Add validation for other editable fields if needed
        ]);

        $user->update($validatedData);
        return new UserDetailResource($user);
    }
}
