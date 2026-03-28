<?php

namespace App\Http\Controllers;

use App\Events\UserCreated;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;

class GoogleLoginController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error') || !$request->has('code')) {
            return redirect(config('app.url').'/login');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed: '.$e->getMessage());

            return redirect(config('app.url').'/login');
        }

        $user = User::where('email', $googleUser->email)->first();

        if (!$user || !$user->has_signed_up) {
            $photoUrl = null;

            try {
                // SECURITY: Validate avatar URL to prevent SSRF attacks
                $avatarUrl = $googleUser->avatar;

                if (filter_var($avatarUrl, FILTER_VALIDATE_URL) &&
                    str_starts_with($avatarUrl, 'https://')) {
                    // Use HTTP client with timeout and security constraints
                    $response = Http::timeout(5)
                        ->withOptions([
                            'max_redirects' => 0,  // Prevent redirect attacks
                            'verify' => true,       // Verify SSL certificates
                        ])
                        ->get($avatarUrl);

                    // Validate response size (5MB limit)
                    if ($response->successful() &&
                        strlen($response->body()) < 5242880 &&
                        $response->header('Content-Type') &&
                        str_starts_with($response->header('Content-Type'), 'image/')) {
                        $photoContents = $response->body();

                        // Server-side image validation
                        $image = @imagecreatefromstring($photoContents);
                        if ($image !== false) {
                            imagedestroy($image);

                            // Save validated image
                            $filename = 'user_'.uniqid().'.jpg';
                            $photoPath = 'profile/'.$filename;
                            Storage::disk('media')->put($photoPath, $photoContents);
                            $photoUrl = Storage::disk('media')->url($photoPath);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log security event
                Log::warning('Failed to fetch Google avatar', [
                    'url' => $googleUser->avatar ?? 'null',
                    'error' => $e->getMessage(),
                ]);
                // Keep photoUrl as null
            }

            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'photo' => $photoUrl,
                    'tos_agreed_at' => now(), // Assume implicit agreement via UI gatekeeping
                ]);
                // Explicitly set is_active since it's guarded
                $user->is_active = true;
                $user->save();

                event(new UserCreated($user));
            } else {
                $user->update([
                    'name' => $googleUser->name,
                    'photo' => $photoUrl,
                ]);

                if (!$user->is_active) {
                    $user->is_active = true;
                    $user->save();
                    event(new UserCreated($user));
                }
            }
        }

        Auth::login($user);

        return redirect(config('app.url'));
    }
}
