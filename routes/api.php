<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ApiIsAuthenticated;
use App\Http\Middleware\ApiIsAdmin;
use App\Http\Resources\UserDetailResource;
use App\Http\Controllers\UserController;
use App\Http\Controllers\TripController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

Route::get('/caves', [App\Http\Controllers\CaveController::class, 'index']);
Route::post('/caves', [App\Http\Controllers\CaveController::class, 'store'])->middleware(ApiIsAdmin::class);
Route::get('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'show']);
Route::put('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'update'])->middleware(ApiIsAdmin::class);

Route::get('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'show']);
Route::put('/cave_systems/{cave_system}', [App\Http\Controllers\CaveSystemController::class, 'update'])->middleware(ApiIsAdmin::class);


Route::get('/trips', [App\Http\Controllers\TripController::class, 'index']);
Route::post('/trips', [App\Http\Controllers\TripController::class, 'store']);
Route::get('/trips/{trip}', [App\Http\Controllers\TripController::class, 'show']);
Route::put('/trips/{trip}', [App\Http\Controllers\TripController::class, 'update']);
Route::delete('/trips/{trip}', [App\Http\Controllers\TripController::class, 'destroy']);

Route::get('/me/trips', [App\Http\Controllers\TripController::class, 'indexMe'])->middleware(ApiIsAuthenticated::class);
Route::get('/me/trips/download', [TripController::class, 'downloadMyTripsCsv'])->middleware(ApiIsAuthenticated::class);;

# Users
Route::get('/users', action: [App\Http\Controllers\UserController::class, 'index'])->name('users.index');

// Admin User Endpoints
Route::get('/admin/users', [UserController::class, 'adminIndex'])
    ->middleware(ApiIsAdmin::class)
    ->name('admin.users.index');
Route::put('/admin/users/{user_without_scopes}/toggle-approval', [UserController::class, 'toggleApproval'])
    ->middleware(ApiIsAdmin::class)
    ->withoutScopedBindings() // Disable global scopes for this route model binding
    ->name('admin.users.toggle-approval');
Route::put('/admin/users/{user_without_scopes}/toggle-admin', [UserController::class, 'toggleAdmin'])
    ->middleware(ApiIsAdmin::class)
    ->withoutScopedBindings() // Disable global scopes for this route model binding
    ->name('admin.users.toggle-admin');

Route::get('/users/me', function (Request $request) {
    if($request->user()) {
        return new UserDetailResource($request->user());
    } else {
        return abort(400, 'No user logged in');
    }
});

Route::get('logout', function (Request $request) {
    Auth::logout();
    return redirect('/');
});

Route::get('/tags', [App\Http\Controllers\TagsController::class, 'index'])->name('tags.index');


Route::get('/news', [App\Http\Controllers\NewsController::class, 'index'])->name('news.index');

Route::post('/users', action: [App\Http\Controllers\UserController::class, 'create'])->name('users.create');
Route::get('/users/{user}', action: [App\Http\Controllers\UserController::class, 'show'])->name('users.show');
Route::put('/users/{user}', action: [App\Http\Controllers\UserController::class, 'store'])->name('users.store');

Route::get('/livez', function(Request $request) {
    try {
        $result = DB::table('users')->raw(DB::raw("SELECT 1 + 1 AS result"));
        return response()->json(['status' => 'ok', 'result' => $result], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});