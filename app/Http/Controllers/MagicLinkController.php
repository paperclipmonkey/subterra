<?php

namespace App\Http\Controllers;

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;

class MagicLinkController extends Controller
{
    /**
     * Send a magic link to the provided email address.
     */
    public function sendMagicLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        try {
            // Find existing user or create new one
            // Use withoutGlobalScope to bypass IsActiveScope and find any user with this email
            $user = User::withoutGlobalScope(\App\Models\Scopes\IsActiveScope::class)
                        ->where('email', $email)
                        ->first();

            if (!$user) {
                // Create new user with minimal required fields
                $user = User::create([
                    'email' => $email,
                    'name' => null, // Will be set later in profile
                    'tos_agreed_at' => $request->boolean('agreed_to_tos') ? now() : null,
                    'privacy_policy_agreed_at' => $request->boolean('agreed_to_tos') ? now() : null,
                ]);

                // Explicitly set guarded fields
                $user->is_active = true; // Enable the user since they're requesting access
                $user->save();

                event(new \App\Events\UserCreated($user));
            } else {
                // If user exists but is inactive, reactivate them
                if (!$user->is_active) {
                    $user->is_active = true;
                    $user->save();
                    event(new \App\Events\UserCreated($user));
                }
            }

            // Note: In v2.25+, actions are serialized with HMAC signing, making it
            // impractical to query by user ID. We rely on the lifetime expiry instead.
            // Old links will expire automatically based on their configured lifetime.

            // Create new magic link for login (30 minutes lifetime)
            $magiclink = MagicLink::create(new LoginAction($user), 30);

            // Send the magic link via our custom Mailable
            Mail::to($email)->send(new MagicLinkMail($magiclink->url));

            Log::info('Magic link sent', [
                'email' => $email,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Magic link sent! Check your email.',
                'success' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send magic link', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to send magic link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle magic link callback and authenticate user via API.
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $token = $request->query('token');

            if (!$token) {
                return response()->json([
                    'error' => 'Token is required',
                ], 400);
            }

            // Use the MagicLink model to get and validate the token
            $magicLink = \MagicLink\MagicLink::getValidMagicLinkByToken($token);

            if (!$magicLink) {
                return response()->json([
                    'error' => 'Invalid or expired magic link',
                ], 401);
            }

            // Get the action - this will automatically deserialize using the new format
            $action = $magicLink->action;

            if (!$action instanceof \MagicLink\Actions\LoginAction) {
                return response()->json([
                    'error' => 'Invalid magic link action',
                ], 400);
            }

            // Get the user ID from the LoginAction using reflection
            $reflection = new \ReflectionClass($action);
            $authIdentifierProperty = $reflection->getProperty('authIdentifier');
            $authIdentifierProperty->setAccessible(true);
            $userId = $authIdentifierProperty->getValue($action);

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                ], 404);
            }

            // Authenticate the user using Laravel's session-based auth
            Auth::login($user);

            // Mark the magic link as visited (increments counter)
            $magicLink->visited();

            Log::info('User authenticated via magic link', ['user_id' => $user->id]);

            // Check if user needs to complete their profile
            $needsProfile = empty($user->name);

            return response()->json([
                'message' => 'Authentication successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,

                ],
                'needs_profile' => $needsProfile,
            ]);
        } catch (\Exception $e) {
            Log::error('Magic link callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Authentication failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle magic link web callback (from email link)
     * This redirects to the frontend with authentication.
     */
    public function handleWebCallback(Request $request)
    {
        try {
            // The magic link middleware will handle authentication
            // We just need to redirect to the frontend callback page
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $callbackUrl = $frontendUrl.'/auth/magic-link-callback?'.http_build_query($request->query());

            return redirect($callbackUrl);
        } catch (\Exception $e) {
            Log::error('Magic link web callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            // Redirect to frontend with error
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            return redirect($frontendUrl.'/?error=magic_link_failed');
        }
    }
}
