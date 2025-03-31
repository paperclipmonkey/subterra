<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserDetailResource;
use Illuminate\Http\Request;
use App\Http\Requests\StoreTripRequest;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        return UserResource::collection(User::all());
    }

    public function create(StoreTripRequest $request) {
        $newUser = $request->all();
        $user = User::withoutGlobalScopes()->firstOrCreate(['email' => $newUser['email']], [
            'name' => $newUser['name'],
            'is_active' => false,
            'photo' => Storage::disk('media')->url('profile/default.webp'),
        ]);    
    }

    public function show(User $user)
    {
        return new UserDetailResource($user);
    }

    public function store(User $user, Request $request) {
        $user->update([
            'club' => $request->all()['club'],
        ]);
        return new UserDetailResource($user);
    }
}
