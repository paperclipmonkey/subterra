<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClubDataController;
use App\Http\Middleware\ApiIsAuthenticated;
use App\Http\Middleware\ClubAdminOrAdmin;
use App\Http\Middleware\ApiIsAdmin;
use App\Http\Middleware\CurrentUserOrAdmin;
use App\Http\Resources\UserDetailEmailResource;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\ClubController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\MagicLinkController;

Route::get('/users/me', function (Request $request) {
    if($request->user()) {
        return new UserDetailEmailResource($request->user());
    } else {
        return abort(400, 'No user logged in');
    }
})->name('users.me');

// Magic link authentication routes (no auth required)
Route::post('/auth/magic-link', [MagicLinkController::class, 'sendMagicLink']);
Route::get('/auth/magic-link-callback', [MagicLinkController::class, 'handleCallback']);

Route::middleware(ApiIsAuthenticated::class)->group(function () {
    Route::post('/clubs/{club}/join', [ClubController::class, 'requestJoin'])->name('clubs.join');
    # Users
    Route::get('/users', action: [App\Http\Controllers\UserController::class, 'index'])->name('users.index');

    Route::get('/caves', [App\Http\Controllers\CaveController::class, 'index']);
    Route::get('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'show']);
    
    Route::post('/caves', [App\Http\Controllers\CaveController::class, 'store'])->middleware(ApiIsAdmin::class);
    Route::put('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'update'])->middleware(ApiIsAdmin::class);

    Route::get('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'show']);
    Route::put('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'update'])->middleware(ApiIsAdmin::class);
    Route::post('/cave_systems_with_cave', [App\Http\Controllers\CaveSystemController::class, 'storeWithCave'])->middleware(ApiIsAdmin::class);

    # Trips
    Route::get('/trips', [App\Http\Controllers\TripController::class, 'index']);
    Route::post('/trips', [App\Http\Controllers\TripController::class, 'store']);
    Route::get('/trips/{trip}', [App\Http\Controllers\TripController::class, 'show']);
    Route::put('/trips/{trip}', [App\Http\Controllers\TripController::class, 'update']);
    Route::delete('/trips/{trip}', [App\Http\Controllers\TripController::class, 'destroy']);

    # My Trips
    Route::get('/me/trips', [App\Http\Controllers\TripController::class, 'indexMe']);
    Route::get('/me/trips/download', [TripController::class, 'downloadMyTripsCsv']);

    # Clubs
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
    Route::delete('/users/{user}', [App\Http\Controllers\UserController::class, 'destroy'])->middleware(CurrentUserOrAdmin::class)->name('users.destroy');

    // --- Club Admin Pending Member Management ---
    Route::get('/admin/clubs/{club}/pending-members', [ClubController::class, 'getPendingMembers'])->middleware(ClubAdminOrAdmin::class)->name('admin.clubs.pending.index');
    Route::put('/admin/clubs/{club}/members/{user}/approve', [ClubController::class, 'approveMember'])->middleware(ClubAdminOrAdmin::class)->name('admin.clubs.members.approve');
    Route::put('/admin/clubs/{club}/members/{user}/reject', [ClubController::class, 'rejectMember'])->middleware(ClubAdminOrAdmin::class)->name('admin.clubs.members.reject');
});


// --- Admin Routes ---
Route::prefix('admin')->middleware(ApiIsAdmin::class)->group(function () {
    Route::get('/users', [UserController::class, 'adminIndex'])->name('admin.users.index');
    Route::put('/users/{user_without_scopes}/toggle-approval', [UserController::class, 'toggleApproval'])
        ->withoutScopedBindings()
        ->name('admin.users.toggle-approval');
    Route::put('/users/{user_without_scopes}/toggle-admin', [UserController::class, 'toggleAdmin'])
        ->withoutScopedBindings()
        ->name('admin.users.toggle-admin');

    // --- Admin Club Endpoints ---
    Route::get('/clubs', [ClubController::class, 'adminIndex'])->name('admin.clubs.index');
    Route::post('/clubs', [ClubController::class, 'store'])->name('admin.clubs.store');
    Route::put('/clubs/{club}', [ClubController::class, 'update'])->name('admin.clubs.update');
    Route::delete('/clubs/{club}', [ClubController::class, 'destroy'])->name('admin.clubs.destroy');
    Route::put('/clubs/{club}/toggle-active', [ClubController::class, 'toggleActive'])->name('admin.clubs.toggle-active');

    // --- Admin Club Member Management Endpoints ---
    // This now gets *approved* members for the main admin list
    Route::get('/clubs/{club}/members', [ClubController::class, 'getApprovedMembers'])->name('admin.clubs.members.index');
    // This syncs *approved* members and their admin status
    Route::put('/clubs/{club}/members', [ClubController::class, 'syncApprovedMembers'])->name('admin.clubs.members.sync');
});

Route::middleware(['auth:sanctum'])->prefix('clubs/{club}')->group(function () {
    Route::get('recent-trips', [ClubDataController::class, 'recentTrips'])->middleware('can:view,club');
    Route::get('members', [ClubDataController::class, 'members'])->middleware('can:view,club');
    Route::get('activity-heatmap', [ClubDataController::class, 'activityHeatmap'])->middleware('can:view,club');
});

Route::get('logout', function (Request $request) {
    Auth::logout();
    return redirect('/');
});

Route::get('/news', [App\Http\Controllers\NewsController::class, 'index'])->name('news.index');

Route::get('/livez', function(Request $request) {
    try {
        $result = DB::table('users')->raw(DB::raw("SELECT 1 + 1 AS result"));
        return response()->json(['status' => 'ok', 'result' => $result], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});