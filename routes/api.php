<?php

declare(strict_types=1);

use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubDataController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\HutController;
use App\Http\Controllers\MagicLinkController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\ApiIsAdmin;
use App\Http\Middleware\ApiIsAuthenticated;
use App\Http\Middleware\ClubAdminOrAdmin;
use App\Http\Middleware\CurrentUserOrAdmin;
use App\Http\Middleware\PipAccess;
use App\Http\Resources\UserDetailEmailResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Twilio inbound webhooks. Authenticated by a shared secret in the URL path (Twilio
// cannot send a custom header). Configure these URLs in the Twilio console.
Route::post('/webhooks/twilio/{secret}/sms', [\App\Http\Controllers\Webhook\TwilioController::class, 'handleSms'])
    ->middleware('throttle:webhook-twilio-sms')->name('webhooks.twilio.sms');
Route::post('/webhooks/twilio/{secret}/sms/status', [\App\Http\Controllers\Webhook\TwilioController::class, 'handleSmsStatus'])
    ->middleware('throttle:webhook-twilio-sms')->name('webhooks.twilio.sms.status');
Route::post('/webhooks/twilio/{secret}/voice', [\App\Http\Controllers\Webhook\TwilioController::class, 'voiceTwiml'])
    ->middleware('throttle:webhook-twilio-voice')->name('webhooks.twilio.voice');
Route::post('/webhooks/twilio/{secret}/voice/gather', [\App\Http\Controllers\Webhook\TwilioController::class, 'voiceGather'])
    ->middleware('throttle:webhook-twilio-voice')->name('webhooks.twilio.voice.gather');
Route::post('/webhooks/twilio/{secret}/voice/test', [\App\Http\Controllers\Webhook\TwilioController::class, 'voiceTest'])
    ->middleware('throttle:webhook-twilio-voice')->name('webhooks.twilio.voice.test');

Route::post('/webhooks/gcp/media', [\App\Http\Controllers\Webhook\GcpMediaWebhookController::class, 'handle'])
    ->middleware('throttle:webhook-gcp-media');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/callouts', [App\Http\Controllers\CalloutController::class, 'store']);
    Route::get('/callouts/active', [App\Http\Controllers\CalloutController::class, 'active']);
    Route::get('/callouts/contact-numbers', [App\Http\Controllers\CalloutController::class, 'contactNumbers']);
});

// Guest accessible callout routes
Route::get('/callouts/{id}', [App\Http\Controllers\CalloutController::class, 'show']);
// Deliberately NOT rate-limited. This is a life-safety "I am safe" action — blocking
// a legitimate cancellation could trigger a false rescue, which is far worse than any
// abuse. The 16-char random callout id is a capability token that makes enumeration
// infeasible, and CalloutService::cancel() is idempotent, so repeated calls are
// harmless no-ops. See the controller docblock for the security rationale.
Route::post('/callouts/{id}/cancel', [App\Http\Controllers\CalloutController::class, 'cancel']);

Route::get('/users/me', function (Request $request) {
    $user = $request->user();
    $user->load(['clubs', 'medals', 'roles', 'trips.system']);

    return new UserDetailEmailResource($user);
})->middleware('auth:sanctum')->name('users.me');
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/users/me', [App\Http\Controllers\UserController::class, 'updateMe'])->name('users.me.update');
    Route::post('/users/me', [App\Http\Controllers\UserController::class, 'updateMe'])->name('users.me.update.post');
    Route::delete('/users/me', [App\Http\Controllers\UserController::class, 'destroyMe'])->name('users.me.destroy');

    // Phone-number verification (send a code by SMS, then confirm it).
    Route::post('/users/me/phone/send-code', [App\Http\Controllers\PhoneVerificationController::class, 'sendCode'])
        ->middleware('throttle:phone-verify-send')->name('users.me.phone.send-code');
    Route::post('/users/me/phone/verify', [App\Http\Controllers\PhoneVerificationController::class, 'verify'])
        ->middleware('throttle:phone-verify')->name('users.me.phone.verify');
});

// Magic link authentication routes (no auth required)
Route::post('/auth/magic-link', [MagicLinkController::class, 'sendMagicLink'])
    ->middleware('throttle:magic-link');
Route::get('/auth/magic-link-callback', [MagicLinkController::class, 'handleCallback']);

// Public CMS Pages
Route::get('/pages/{page}', [App\Http\Controllers\PageController::class, 'publicShow'])
    ->middleware(\App\Http\Middleware\TrackApiInteraction::class.':'.\App\Models\Page::class);

Route::get('/cave_systems/{cave_system}/routes', [App\Http\Controllers\RouteController::class, 'index']);
Route::get('/routes/{route}', [App\Http\Controllers\RouteController::class, 'show']);

Route::middleware(['auth:sanctum', ApiIsAuthenticated::class])->group(function () {
    Route::post('/clubs/{club}/join', [ClubController::class, 'requestJoin'])->name('clubs.join');

    Route::post('/corrections', [App\Http\Controllers\CorrectionController::class, 'store']);
    Route::post('/suggested-edits', [App\Http\Controllers\SuggestedEditController::class, 'store']);

    // Users
    Route::get('/users', [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/duty-officers/current', [App\Http\Controllers\DutyOfficerController::class, 'current']);
    Route::get('/duty-officers/rota', [App\Http\Controllers\DutyOfficerController::class, 'rotaPublic']);

    Route::get('/caves', [App\Http\Controllers\CaveController::class, 'index']);
    Route::get('/caves/search', [App\Http\Controllers\CaveController::class, 'search']);
    Route::get('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'show'])
        ->middleware(\App\Http\Middleware\TrackApiInteraction::class.':'.\App\Models\Cave::class);
    Route::get('/caves/{cave}/weather/forecast', [App\Http\Controllers\CaveWeatherController::class, 'forecast']);
    Route::get('/caves/{cave}/weather/historic', [App\Http\Controllers\CaveWeatherController::class, 'historic']);

    // Authorization is handled by CavePolicy inside the FormRequests / controller
    // (data_admin), which also covers admin-only visibility and private fields.
    Route::post('/caves', [App\Http\Controllers\CaveController::class, 'store']);
    Route::put('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'update']);
    Route::delete('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'destroy']);
    // Restore a soft-deleted cave (withTrashed so binding resolves the trashed record).
    Route::post('/caves/{cave}/restore', [App\Http\Controllers\CaveController::class, 'restore'])->withTrashed();

    Route::get('/cave_systems', [App\Http\Controllers\CaveSystemController::class, 'index']);
    Route::get('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'show']);
    Route::put('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'update'])->middleware(ApiIsAdmin::class);
    Route::post('/cave_systems_with_cave', [App\Http\Controllers\CaveSystemController::class, 'storeWithCave'])->middleware(ApiIsAdmin::class);

    // Cave-system files — the single home for extra media & documents (surveys,
    // historic photos, reports). Public ones surface on the system/cave pages;
    // private files and uploads/deletes are restricted to data admins
    // (enforced in the controller).
    Route::get('/cave_systems/{cave_system}/files', [App\Http\Controllers\CaveSystemFileController::class, 'index']);
    Route::post('/cave_systems/{cave_system}/files', [App\Http\Controllers\CaveSystemFileController::class, 'store']);
    Route::delete('/cave_systems/{cave_system}/files/{file}', [App\Http\Controllers\CaveSystemFileController::class, 'destroy']);

    // Cave System Annotations
    Route::get('/cave_systems/{cave_system}/annotations', [App\Http\Controllers\CaveSystemAnnotationController::class, 'show']);
    Route::post('/cave_systems/{cave_system}/annotations', [App\Http\Controllers\CaveSystemAnnotationController::class, 'store'])->middleware(ApiIsAdmin::class);
    Route::delete('/cave_systems/{cave_system}/annotations', [App\Http\Controllers\CaveSystemAnnotationController::class, 'destroy'])->middleware(ApiIsAdmin::class);

    Route::post('/cave_systems/{cave_system}/routes', [App\Http\Controllers\RouteController::class, 'store'])->middleware(ApiIsAdmin::class);
    Route::put('/routes/{route}', [App\Http\Controllers\RouteController::class, 'update'])->middleware(ApiIsAdmin::class);
    Route::delete('/routes/{route}', [App\Http\Controllers\RouteController::class, 'destroy'])->middleware(ApiIsAdmin::class);

    // Trips
    Route::get('/trips', [App\Http\Controllers\TripController::class, 'index']);
    Route::post('/trips', [App\Http\Controllers\TripController::class, 'store']);
    Route::put('/trips/{trip}', [App\Http\Controllers\TripController::class, 'update']);
    Route::delete('/trips/{trip}', [App\Http\Controllers\TripController::class, 'destroy']);

    // My Trips
    Route::get('/me/trips', [App\Http\Controllers\TripController::class, 'indexMe']);
    Route::get('/me/trips/download', [TripController::class, 'downloadMyTripsCsv']);

    // My Medals — earned plus progress toward the rest
    Route::get('/me/medals', [App\Http\Controllers\MedalController::class, 'indexMe'])->name('medals.me');

    // Clubs
    Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');

    Route::get('/users/{user}/recent-trips', [UserController::class, 'recentTrips'])->name('users.recent-trips');
    Route::get('/users/{user}/activity-heatmap', [UserController::class, 'activityHeatmap'])->name('users.activity-heatmap');
    Route::get('/users/{user}/medals', [UserController::class, 'medals'])->name('users.medals');

    Route::get('/tags', [App\Http\Controllers\TagsController::class, 'index'])->name('tags.index');

    // User Management
    Route::post('/users', [App\Http\Controllers\UserController::class, 'create'])->middleware('throttle:user-create')->name('users.create');
    Route::get('/users/{user}', [App\Http\Controllers\UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', [App\Http\Controllers\UserController::class, 'store'])->middleware(CurrentUserOrAdmin::class)->name('users.store');
    Route::get('/user/export', [App\Http\Controllers\UserController::class, 'export'])->name('users.export');
    Route::delete('/users/{user_without_scopes}', [App\Http\Controllers\UserController::class, 'destroy'])->middleware(CurrentUserOrAdmin::class)->name('users.destroy');

    // --- Club Admin Pending Member Management ---
    Route::get('/admin/clubs/{club}/pending-members', [ClubController::class, 'getPendingMembers'])->middleware(ClubAdminOrAdmin::class)->name('admin.clubs.pending.index');
    Route::put('/admin/clubs/{club}/members/{user}/approve', [ClubController::class, 'approveMember'])->middleware(ClubAdminOrAdmin::class)->name('admin.clubs.members.approve');
    Route::put('/admin/clubs/{club}/members/{user}/reject', [ClubController::class, 'rejectMember'])->middleware(ClubAdminOrAdmin::class)->name('admin.clubs.members.reject');

    // Huts
    Route::apiResource('huts', HutController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

    // Collections
    Route::apiResource('collections', CollectionController::class)->except(['show']);
    Route::get('collections/{collection}', [CollectionController::class, 'show'])
        ->middleware(\App\Http\Middleware\TrackApiInteraction::class.':'.\App\Models\Collection::class)
        ->name('collections.show');
    Route::post('collections/{collection}/caves', [CollectionController::class, 'addCave']);
    Route::delete('collections/{collection}/caves/{cave}', [CollectionController::class, 'removeCave']);

    // Permits & Bookings (public-facing)
    Route::get('/permits', [BookingController::class, 'publicPermits']);
    Route::get('/caves/{cave}/permit', [BookingController::class, 'permitForCave']);
    Route::get('/permits/{permit}', [BookingController::class, 'showPermit']);
    Route::get('/permits/{permit}/calendar', [BookingController::class, 'calendarForPermit']);
    Route::post('/permits/{permit}/bookings', [BookingController::class, 'store']);
    Route::get('/bookings/mine', [BookingController::class, 'mine']);
    Route::put('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
});

// Public Trip Access
Route::get('/trips/{trip}', [App\Http\Controllers\TripController::class, 'show'])
    ->middleware(\App\Http\Middleware\TrackApiInteraction::class.':'.\App\Models\Trip::class);

// Public embeddable permit calendar (availability only — no personal data).
// Powers the iframe embed an access officer can drop into an external website.
Route::get('/embed/permits/{permit}/calendar', [BookingController::class, 'embedCalendar']);

// --- AI Assistant (Pip) ---
// Open to platform_admin OR users granted the `pip_access` role explicitly.
// Rate-limited to 50 requests per day (1440 min).
Route::post('/assistant/chat', [App\Http\Controllers\AssistantController::class, 'chat'])
    ->middleware(['auth:sanctum', ApiIsAuthenticated::class, PipAccess::class])
    ->middleware('throttle:assistant-chat')
    ->name('assistant.chat');

Route::post('/assistant/agreement', [App\Http\Controllers\AssistantController::class, 'acceptAgreement'])
    ->middleware(['auth:sanctum', ApiIsAuthenticated::class, PipAccess::class])
    ->name('assistant.agreement');

Route::post('/assistant/feedback', [App\Http\Controllers\AssistantController::class, 'feedback'])
    ->middleware(['auth:sanctum', ApiIsAuthenticated::class, PipAccess::class])
    ->middleware('throttle:assistant-feedback')
    ->name('assistant.feedback');

Route::post('/assistant/logbook-import', [App\Http\Controllers\AssistantController::class, 'importLogbook'])
    ->middleware(['auth:sanctum', ApiIsAuthenticated::class, PipAccess::class])
    ->middleware('throttle:assistant-logbook-import')
    ->name('assistant.logbook-import');

// --- Admin Routes ---
Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    // Platform Admin — users, clubs, pages, comms, tasks, dashboard, suggested edits
    Route::middleware(ApiIsAdmin::class.':platform_admin')->group(function () {
        Route::get('/users', [UserController::class, 'adminIndex'])->name('admin.users.index');
        Route::put('/users/{user_without_scopes}/toggle-admin', [UserController::class, 'toggleAdmin'])
            ->withoutScopedBindings()
            ->name('admin.users.toggle-admin');
        Route::put('/users/{user_without_scopes}/toggle-role/{role}', [UserController::class, 'toggleRole'])
            ->withoutScopedBindings()
            ->name('admin.users.toggle-role');

        // Pip feedback (flagged conversations) review UI
        Route::get('/pip-feedback', [App\Http\Controllers\Admin\PipFeedbackController::class, 'index'])->name('admin.pip-feedback.index');
        Route::get('/pip-feedback/{feedback}', [App\Http\Controllers\Admin\PipFeedbackController::class, 'show'])->name('admin.pip-feedback.show');
        Route::put('/pip-feedback/{feedback}/reviewed', [App\Http\Controllers\Admin\PipFeedbackController::class, 'markReviewed'])->name('admin.pip-feedback.reviewed');

        Route::get('/clubs', [ClubController::class, 'adminIndex'])->name('admin.clubs.index');
        Route::post('/clubs', [ClubController::class, 'store'])->name('admin.clubs.store');
        Route::put('/clubs/{club}', [ClubController::class, 'update'])->name('admin.clubs.update');
        Route::delete('/clubs/{club}', [ClubController::class, 'destroy'])->name('admin.clubs.destroy');
        Route::put('/clubs/{club}/toggle-active', [ClubController::class, 'toggleActive'])->name('admin.clubs.toggle-active');
        Route::get('/clubs/{club}/members', [ClubController::class, 'getApprovedMembers'])->name('admin.clubs.members.index');
        Route::put('/clubs/{club}/members', [ClubController::class, 'syncApprovedMembers'])->name('admin.clubs.members.sync');
        Route::post('/communications/send', [App\Http\Controllers\Admin\CommunicationController::class, 'send'])->name('admin.communications.send');
        Route::get('/dashboard/popular-records', [App\Http\Controllers\Admin\DashboardController::class, 'popularRecords'])->name('admin.dashboard.popular-records');
        Route::get('/dashboard/metrics-overview', [App\Http\Controllers\Admin\DashboardController::class, 'metricsOverview'])->name('admin.dashboard.metrics-overview');
    });

    // Content & Data shared (Platform Admin OR Data Admin)
    Route::middleware(ApiIsAdmin::class.':platform_admin,data_admin')->group(function () {
        Route::get('/tasks', [App\Http\Controllers\Admin\TaskController::class, 'index'])->name('admin.tasks.index');
        Route::post('/cave-registry-sync/{registry}', [App\Http\Controllers\Admin\CaveRegistrySyncController::class, 'dispatch'])->name('admin.cave-registry-sync');
        Route::apiResource('pages', App\Http\Controllers\PageController::class);
        Route::get('/suggested-edits/batches', [App\Http\Controllers\Admin\SuggestedEditController::class, 'batches']);
        Route::post('/suggested-edits/batches/{batchId}/approve', [App\Http\Controllers\Admin\SuggestedEditController::class, 'approveBatch']);
        Route::post('/suggested-edits/batches/{batchId}/reject', [App\Http\Controllers\Admin\SuggestedEditController::class, 'rejectBatch']);
        Route::apiResource('suggested-edits', App\Http\Controllers\Admin\SuggestedEditController::class)->only(['index', 'show']);
        Route::post('/suggested-edits/{suggested_edit}/approve', [App\Http\Controllers\Admin\SuggestedEditController::class, 'approve']);
        Route::post('/suggested-edits/{suggested_edit}/reject', [App\Http\Controllers\Admin\SuggestedEditController::class, 'reject']);
        Route::apiResource('catchments', App\Http\Controllers\CatchmentController::class);

        // Cave System merge
        Route::get('/cave-systems/{cave_system}/merge-preview', [App\Http\Controllers\Admin\CaveSystemController::class, 'mergePreview']);
        Route::post('/cave-systems/{cave_system}/merge', [App\Http\Controllers\Admin\CaveSystemController::class, 'merge']);
        Route::delete('/cave-systems/{cave_system}', [App\Http\Controllers\Admin\CaveSystemController::class, 'destroy']);
    });

    // Access Officer — permits, bookings
    Route::middleware(ApiIsAdmin::class.':access_officer,platform_admin')->group(function () {
        Route::get('/users/officer-list', [UserController::class, 'officerList'])->name('admin.users.officer-list');

        Route::get('/permits', [App\Http\Controllers\Admin\PermitController::class, 'index'])->name('admin.permits.index');
        Route::post('/permits', [App\Http\Controllers\Admin\PermitController::class, 'store'])->name('admin.permits.store');
        Route::get('/permits/{permit}', [App\Http\Controllers\Admin\PermitController::class, 'show'])->name('admin.permits.show');
        Route::put('/permits/{permit}', [App\Http\Controllers\Admin\PermitController::class, 'update'])->name('admin.permits.update');
        Route::post('/permits/{permit}/photo', [App\Http\Controllers\Admin\PermitController::class, 'uploadPhoto'])->name('admin.permits.photo.upload');
        Route::delete('/permits/{permit}/photo', [App\Http\Controllers\Admin\PermitController::class, 'deletePhoto'])->name('admin.permits.photo.delete');
        Route::delete('/permits/{permit}', [App\Http\Controllers\Admin\PermitController::class, 'destroy'])->name('admin.permits.destroy');

        Route::get('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'index'])->name('admin.bookings.index');
        Route::post('/bookings', [App\Http\Controllers\Admin\BookingController::class, 'adminStore'])->name('admin.bookings.store');
        Route::put('/bookings/{booking}/approve', [App\Http\Controllers\Admin\BookingController::class, 'approve'])->name('admin.bookings.approve');
        Route::put('/bookings/{booking}/reject', [App\Http\Controllers\Admin\BookingController::class, 'reject'])->name('admin.bookings.reject');
        Route::put('/bookings/{booking}/cancel', [App\Http\Controllers\Admin\BookingController::class, 'cancel'])->name('admin.bookings.cancel');
        Route::post('/bookings/{booking}/message', [App\Http\Controllers\Admin\BookingController::class, 'message'])->name('admin.bookings.message');
    });

    // Duty Officer — callouts, shifts, incidents
    Route::middleware(ApiIsAdmin::class.':duty_officer,platform_admin')->group(function () {
        Route::get('/duty-officers', [App\Http\Controllers\DutyOfficerController::class, 'index'])->name('admin.duty-officers.index');
        Route::get('/callouts', [App\Http\Controllers\Admin\CalloutController::class, 'index'])->name('admin.callouts.index');
        Route::post('/callouts/test-watchdog', [App\Http\Controllers\Admin\CalloutController::class, 'sendTestWatchdogCallout'])->name('admin.callouts.test-watchdog');

        // Duty officers test the alert channels (SMS + voice) to build confidence.
        Route::post('/duty-officers/test-self', [App\Http\Controllers\Admin\DutyOfficerTestController::class, 'testSelf'])
            ->middleware('throttle:duty-officer-test-self')->name('admin.duty-officers.test-self');
        Route::post('/duty-officers/test-broadcast', [App\Http\Controllers\Admin\DutyOfficerTestController::class, 'testBroadcast'])
            ->middleware('throttle:duty-officer-test-broadcast')->name('admin.duty-officers.test-broadcast');
        Route::get('/shifts', [App\Http\Controllers\Admin\OnCallController::class, 'index']);
        Route::post('/shifts', [App\Http\Controllers\Admin\OnCallController::class, 'store']);
        Route::put('/shifts/{id}', [App\Http\Controllers\Admin\OnCallController::class, 'update']);
        Route::delete('/shifts/{id}', [App\Http\Controllers\Admin\OnCallController::class, 'destroy']);

        Route::get('/incidents', [App\Http\Controllers\Admin\IncidentController::class, 'index']);
        Route::get('/incidents/{id}', [App\Http\Controllers\Admin\IncidentController::class, 'show']);
        Route::post('/incidents/{id}/acknowledge', [App\Http\Controllers\Admin\IncidentController::class, 'acknowledge']);
        Route::post('/incidents/{id}/notes', [App\Http\Controllers\Admin\IncidentController::class, 'addNote']);
        Route::post('/incidents/{id}/resolve', [App\Http\Controllers\Admin\IncidentController::class, 'resolve']);
    });
});

Route::middleware(['auth:sanctum'])->prefix('clubs/{club}')->group(function () {
    Route::get('recent-trips', [ClubDataController::class, 'recentTrips'])->middleware('can:view,club');
    Route::get('members', [ClubDataController::class, 'members'])->middleware('can:view,club');
    Route::get('activity-heatmap', [ClubDataController::class, 'activityHeatmap'])->middleware('can:view,club');
    Route::get('summary', [ClubDataController::class, 'summary'])->middleware('can:view,club');
});

Route::post('logout', function (Request $request) {
    // Invalidate the session for SPA/stateful auth
    Auth::guard('web')->logout();

    if ($request->hasSession()) {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    return response()->json(['message' => 'Logged out']);
})->middleware('auth:sanctum');

Route::get('/news', [App\Http\Controllers\NewsController::class, 'index'])->name('news.index');
Route::get('/news/{id}', [App\Http\Controllers\NewsController::class, 'show'])->name('news.show');

Route::get('/livez', function (Request $request) {
    try {
        // Simple health check - just verify DB connection
        DB::select('SELECT 1');

        return response()->json(['status' => 'ok'], 200);
    } catch (\Exception $e) {
        // Log error details internally, but don't expose to client
        \Log::error('Health check failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json(['status' => 'error'], 500);
    }
});
