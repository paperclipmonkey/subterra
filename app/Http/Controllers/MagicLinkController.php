<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\MagicLinkMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use MagicLink\Actions\LoginAction;
use MagicLink\MagicLink;

class MagicLinkController extends Controller
{
    /**
     * Send a magic link to the provided email address
     */
    public function sendMagicLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email'
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
                    'is_active' => true, // Enable the user since they're requesting access
                    'is_approved' => false, // Still needs admin approval
                ]);
            } else {
                // If user exists but is inactive, reactivate them
                if (!$user->is_active) {
                    $user->update(['is_active' => true]);
                }
            }

            // Invalidate any existing magic links for this user by finding all magic links
            // and checking which ones are for LoginAction with this user
            $existingLinks = DB::table('magic_links')->get();
            $deletedCount = 0;
            
            foreach ($existingLinks as $link) {
                try {
                    // Try direct unserialize first (for test environment)
                    $action = unserialize($link->action);
                } catch (\Exception $e) {
                    try {
                        // If that fails, try base64 decode first (for production environment)
                        $action = unserialize(base64_decode($link->action));
                    } catch (\Exception $e2) {
                        // Skip invalid serialized data
                        continue;
                    }
                }
                
                if ($action instanceof \MagicLink\Actions\LoginAction) {
                    // Use reflection to get the user ID from the LoginAction
                    $reflection = new \ReflectionClass($action);
                    $authIdentifierProperty = $reflection->getProperty('authIdentifier');
                    $authIdentifierProperty->setAccessible(true);
                    $actionUserId = $authIdentifierProperty->getValue($action);
                    
                    if ($actionUserId == $user->id) {
                        DB::table('magic_links')->where('id', $link->id)->delete();
                        $deletedCount++;
                    }
                }
            }

            // Create new magic link for login (30 minutes lifetime)
            $magiclink = MagicLink::create(new LoginAction($user), 30);

            // Send the magic link via our custom Mailable
            Mail::to($email)->send(new MagicLinkMail($magiclink->url));

            Log::info('Magic link sent', [
                'email' => $email, 
                'user_id' => $user->id, 
                'invalidated_links_count' => $deletedCount
            ]);

            return response()->json([
                'message' => 'Magic link sent! Check your email.',
                'success' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send magic link', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to send magic link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle magic link callback and authenticate user via API
     */
    public function handleCallback(Request $request): JsonResponse
    {
        try {
            $token = $request->query('token');
            
            if (!$token) {
                return response()->json([
                    'error' => 'Token is required'
                ], 400);
            }
            
            if (strpos($token, ':') !== false) {
                $token = explode(':', $token, 2)[1];
            }

            // Query the magic_links table directly
            $magicLinkData = DB::table('magic_links')
                ->where('token', $token)
                ->first();

            if (!$magicLinkData) {
                return response()->json([
                    'error' => 'Invalid or expired magic link'
                ], 401);
            }

            // Check if the magic link has expired
            // available_at is the expiration time for magic links
            if ($magicLinkData->available_at && now() > $magicLinkData->available_at) {
                return response()->json([
                    'error' => 'Magic link has expired'
                ], 401);
            }

            // Check max visits (default is 1 for LoginAction)
            $maxVisits = $magicLinkData->max_visits ?? 1;
            if ($magicLinkData->num_visits >= $maxVisits) {
                return response()->json([
                    'error' => 'Magic link has been used too many times'
                ], 401);
            }

            // Deserialize the action to get the user
            $action = null;
            try {
                $action = unserialize($magicLinkData->action);
            } catch (\Exception $e) {
                try {
                    $action = unserialize(base64_decode($magicLinkData->action));
                } catch (\Exception $e2) {
                    return response()->json([
                        'error' => 'Invalid magic link action (unserialize failed)'
                    ], 400);
                }
            }
            
            if (!$action instanceof LoginAction) {
                return response()->json([
                    'error' => 'Invalid magic link action'
                ], 400);
            }
            
            // Get the user ID from the LoginAction
            $reflection = new \ReflectionClass($action);
            $authIdentifierProperty = $reflection->getProperty('authIdentifier');
            $authIdentifierProperty->setAccessible(true);
            $userId = $authIdentifierProperty->getValue($action);
            
            $user = User::find($userId);
            
            if (!$user) {
                return response()->json([
                    'error' => 'User not found'
                ], 404);
            }

            // Authenticate the user using Laravel's session-based auth
            Auth::login($user);
            
            // Increment the visits counter
            DB::table('magic_links')
                ->where('id', $magicLinkData->id)
                ->increment('num_visits');
            
            // Delete the magic link if it has reached max visits
            if (($magicLinkData->num_visits + 1) >= $maxVisits) {
                DB::table('magic_links')->where('id', $magicLinkData->id)->delete();
            }

            Log::info('User authenticated via magic link', ['user_id' => $user->id]);
            
            // Check if user needs to complete their profile
            $needsProfile = empty($user->name);
            
            return response()->json([
                'message' => 'Authentication successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_approved' => $user->is_approved,
                ],
                'needs_profile' => $needsProfile
            ]);
            
        } catch (\Exception $e) {
            Log::error('Magic link callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'error' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle magic link web callback (from email link)
     * This redirects to the frontend with authentication
     */
    public function handleWebCallback(Request $request)
    {
        try {
            // The magic link middleware will handle authentication
            // We just need to redirect to the frontend callback page
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $callbackUrl = $frontendUrl . '/auth/magic-link-callback?' . http_build_query($request->query());
            
            return redirect($callbackUrl);
            
        } catch (\Exception $e) {
            Log::error('Magic link web callback error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            // Redirect to frontend with error
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/?error=magic_link_failed');
        }
    }
}
