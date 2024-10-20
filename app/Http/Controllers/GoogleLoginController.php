<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GoogleLoginController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }


    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->stateless()->user();
        $user = User::where('email', $googleUser->email)->first();
        if(!$user)
        {
            try {
            // Make a copy of their profile photo and upload it to the Storage driver for profile photos
            $photoContents = file_get_contents($googleUser->avatar);
            $photoPath = 'profile/' . $googleUser->email . '.jpg';
            Storage::disk('media')->put($photoPath, $photoContents);
            } catch (\Exception $e) {
                // If the photo upload fails, just use the default photo
                $photoPath = 'profile/default.webp';
            }
            // Get the URL for the photo
            $photoUrl = \Storage::disk('media')->url($photoPath);

            $user = User::create([
                'name' => $googleUser->name, 
                'email' => $googleUser->email, 
                'password' => \Hash::make(rand(100000,999999)),
                'photo' => $photoUrl,
            ]);
        }

        Auth::login($user);

        return redirect('http://localhost:3000');
    }
}