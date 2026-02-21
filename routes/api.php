<?php

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
use App\Http\Resources\UserDetailEmailResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/webhooks/clicksend/sms', [\App\Http\Controllers\Webhook\ClickSendController::class, 'handleSms'])
    ->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/callouts', [App\Http\Controllers\CalloutController::class, 'store']);
    Route::get('/callouts/active', [App\Http\Controllers\CalloutController::class, 'active']);
});

// Guest accessible callout routes
Route::get('/callouts/{id}', [App\Http\Controllers\CalloutController::class, 'show']);
Route::post('/callouts/{id}/cancel', [App\Http\Controllers\CalloutController::class, 'cancel'])
    ->middleware('throttle:10,1'); // Max 10 attempts per minute to prevent abuse

Route::get('/users/me', function (Request $request) {
    $user = $request->user();
    $user->load(['clubs', 'medals', 'roles', 'trips.system']);

    return new UserDetailEmailResource($user);
})->middleware('auth:sanctum')->name('users.me');
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/users/me', [App\Http\Controllers\UserController::class, 'updateMe'])->name('users.me.update');
    Route::delete('/users/me', [App\Http\Controllers\UserController::class, 'destroyMe'])->name('users.me.destroy');
});

// Magic link authentication routes (no auth required)
Route::post('/auth/magic-link', [MagicLinkController::class, 'sendMagicLink'])
    ->middleware('throttle:5,1');
Route::get('/auth/magic-link-callback', [MagicLinkController::class, 'handleCallback']);

// Public CMS Pages
Route::get('/pages/{page}', [App\Http\Controllers\PageController::class, 'publicShow'])
    ->middleware(\App\Http\Middleware\TrackApiInteraction::class.':'.\App\Models\Page::class);

Route::get('/cave_systems/{cave_system}/routes', [App\Http\Controllers\RouteController::class, 'index']);
Route::get('/routes/{route}', [App\Http\Controllers\RouteController::class, 'show']);

Route::middleware(ApiIsAuthenticated::class)->group(function () {
    Route::post('/clubs/{club}/join', [ClubController::class, 'requestJoin'])->name('clubs.join');

    Route::post('/corrections', [App\Http\Controllers\CorrectionController::class, 'store']);
    Route::post('/suggested-edits', [App\Http\Controllers\SuggestedEditController::class, 'store']);

    // Users
    Route::get('/users', action: [App\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::get('/duty-officers/current', [App\Http\Controllers\DutyOfficerController::class, 'current']);

    Route::get('/caves', [App\Http\Controllers\CaveController::class, 'index']);
    Route::get('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'show'])
        ->middleware(\App\Http\Middleware\TrackApiInteraction::class.':'.\App\Models\Cave::class);
    Route::get('/caves/{cave}/weather/forecast', [App\Http\Controllers\CaveWeatherController::class, 'forecast']);
    Route::get('/caves/{cave}/weather/historic', [App\Http\Controllers\CaveWeatherController::class, 'historic']);

    Route::post('/caves', [App\Http\Controllers\CaveController::class, 'store'])->middleware(ApiIsAdmin::class.':data_admin');
    Route::put('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'update'])->middleware(ApiIsAdmin::class.':data_admin');

    Route::get('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'show']);
    Route::put('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'update'])->middleware(ApiIsAdmin::class);
    Route::post('/cave_systems_with_cave', [App\Http\Controllers\CaveSystemController::class, 'storeWithCave'])->middleware(ApiIsAdmin::class);

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

    // Clubs
    Route::get('/clubs', [ClubController::class, 'index'])->name('clubs.index');
    Route::get('/clubs/{club}', [ClubController::class, 'show'])->name('clubs.show');

    Route::get('/users/{user}/recent-trips', [UserController::class, 'recentTrips'])->name('users.recent-trips');
    Route::get('/users/{user}/activity-heatmap', [UserController::class, 'activityHeatmap'])->name('users.activity-heatmap');
    Route::get('/users/{user}/medals', [UserController::class, 'medals'])->name('users.medals');

    Route::get('/tags', [App\Http\Controllers\TagsController::class, 'index'])->name('tags.index');

    // User Management
    Route::post('/users', action: [App\Http\Controllers\UserController::class, 'create'])->name('users.create');
    Route::get('/users/{user}', action: [App\Http\Controllers\UserController::class, 'show'])->name('users.show');
    Route::put('/users/{user}', action: [App\Http\Controllers\UserController::class, 'store'])->middleware(CurrentUserOrAdmin::class)->name('users.store');
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
});

// Public Trip Access
Route::get('/trips/{trip}', [App\Http\Controllers\TripController::class, 'show'])
    ->middleware(\App\Http\Middleware\TrackApiInteraction::class.':'.\App\Models\Trip::class);

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
        Route::apiResource('pages', App\Http\Controllers\PageController::class);
        Route::apiResource('suggested-edits', App\Http\Controllers\Admin\SuggestedEditController::class)->only(['index', 'show']);
        Route::post('/suggested-edits/{suggested_edit}/approve', [App\Http\Controllers\Admin\SuggestedEditController::class, 'approve']);
        Route::post('/suggested-edits/{suggested_edit}/reject', [App\Http\Controllers\Admin\SuggestedEditController::class, 'reject']);
        Route::apiResource('catchments', App\Http\Controllers\CatchmentController::class);
    });

    // Duty Officer — callouts, shifts, incidents
    Route::middleware(ApiIsAdmin::class.':duty_officer,platform_admin')->group(function () {
        Route::get('/duty-officers', [App\Http\Controllers\DutyOfficerController::class, 'index'])->name('admin.duty-officers.index');
        Route::get('/callouts', [App\Http\Controllers\Admin\CalloutController::class, 'index'])->name('admin.callouts.index');
        Route::post('/callouts/test-watchdog', [App\Http\Controllers\Admin\CalloutController::class, 'sendTestWatchdogCallout'])->name('admin.callouts.test-watchdog');
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
