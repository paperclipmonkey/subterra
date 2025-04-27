<?php

namespace App\Http\Controllers;

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Log;
use Spatie\SlackAlerts\Facades\SlackAlert;
use App\Events\UserCreated;

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
        if(!$user || !$user->has_signed_up) {
            try {
            // Make a copy of their profile photo and upload it to the Storage driver for profile photos
            $photoContents = file_get_contents($googleUser->avatar);
            $photoPath = 'profile/' . uniqid('user_') . '.jpg';
            Storage::disk('media')->put($photoPath, $photoContents);
            } catch (\Exception $e) {
                // If the photo upload fails, just use the default photo
                $photoPath = 'profile/default.webp';
            }
            // Get the URL for the photo
            $photoUrl = \Storage::disk('media')->url($photoPath);

            if(!$user) {
                $user = User::create([
                    'name' => $googleUser->name, 
                    'email' => $googleUser->email, 
                    'photo' => $photoUrl,
                    'is_active' => true,
                ]);
                event(new UserCreated($user));
            } else {
                $user->update([
                    'name' => $googleUser->name,
                    'photo' => $photoUrl,
                    'is_active' => true,
                ]);
            }
        }

        Auth::login($user);

        return redirect(env('APP_URL'));
    }
}