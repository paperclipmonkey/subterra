<?php

declare(strict_types=1);

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
use Laravel\Socialite\Two\InvalidStateException;

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
            // Not stateless(): Socialite checks the `state` parameter against the
            // one it stored in the session on redirect. Without that check an
            // attacker can feed a victim's browser a callback URL carrying the
            // attacker's own code and sign the victim into the attacker's account
            // (login CSRF).
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            // The state is single-use (pulled from the session), so a replayed
            // callback for a user who already signed in lands here too.
            if (Auth::check()) {
                return redirect(config('app.url'));
            }

            Log::warning('Google OAuth callback rejected: state mismatch');

            return redirect(config('app.url').'/login');
        } catch (\Exception $e) {
            // Google authorization codes are single-use. A double-loaded
            // callback (refresh, back button, double click, link prefetch)
            // makes the second exchange fail with invalid_grant even though
            // the first one already signed the user in — so send them on.
            if (Auth::check() && str_contains($e->getMessage(), 'invalid_grant')) {
                return redirect(config('app.url'));
            }

            Log::error('Google OAuth callback failed: '.$e->getMessage(), [
                'request_url' => $request->url(), // no query string: it carries the auth code
                'code_present' => $request->has('code'),
                'exception' => $e,
            ]);

            return redirect(config('app.url').'/login');
        }

        // Accounts are matched by email alone, so only trust an address Google
        // has verified — otherwise anyone could claim an account (or a
        // placeholder created by trip tagging) by using its address unverified.
        $emailVerified = $googleUser->getRaw()['email_verified'] ?? false;
        if (empty($googleUser->email) || ($emailVerified !== true && $emailVerified !== 'true')) {
            Log::warning('Google OAuth callback rejected: email not verified');

            return redirect(config('app.url').'/login');
        }

        $user = User::withoutGlobalScopes()->where('email', $googleUser->email)->first();

        // Only brand-new users and placeholder accounts (created via trip
        // tagging with no profile yet, or deactivated) go through the signup
        // flow. Established users just log in — their profile is untouched.
        if (!$user || empty($user->name) || empty($user->photo) || !$user->is_active) {
            // Only download the Google avatar when we could actually use it —
            // never to replace an existing photo.
            $photoUrl = (!$user || empty($user->photo))
                ? $this->fetchGoogleAvatar($googleUser->getAvatar())
                : null;

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
                // Fill in missing profile fields only: never overwrite an
                // existing name, and never null out an existing photo when
                // the avatar fetch fails.
                $updates = [];
                if (empty($user->name)) {
                    $updates['name'] = $googleUser->name;
                }
                if (empty($user->photo) && $photoUrl !== null) {
                    $updates['photo'] = $photoUrl;
                }
                if ($updates !== []) {
                    $user->update($updates);
                }

                if (!$user->is_active) {
                    // Reactivating via Google is the user's implicit "sign up"
                    // action, so record ToS agreement just like first-time
                    // creation does (mirrors the magic-link path).
                    if ($user->tos_agreed_at === null) {
                        $user->tos_agreed_at = now();
                    }
                    $user->is_active = true;
                    $user->save();
                    event(new UserCreated($user));
                }
            }
        }

        Auth::login($user);

        return redirect(config('app.url'));
    }

    /**
     * Download and validate a Google avatar, returning the stored URL or null.
     */
    private function fetchGoogleAvatar(?string $avatarUrl): ?string
    {
        try {
            // SECURITY: Validate avatar URL to prevent SSRF attacks
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

                        return Storage::disk('media')->url($photoPath);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log security event
            Log::warning('Failed to fetch Google avatar', [
                'url' => $avatarUrl ?? 'null',
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
