<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserDetailResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return UserResource::collection(User::all());
    }

    public function show(User $user)
    {
        return new UserDetailResource($user);
    }

    public function store(User $user, Request $request){
        $user->update([
            'club' => $request->all()['club'],
        ]);
        return new UserDetailResource($user);
    }
}
