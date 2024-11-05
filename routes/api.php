<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ApiIsAuthenticated;
use App\Http\Middleware\ApiIsAdmin;
use App\Http\Resources\UserDetailResource;

Route::get('/caves', [App\Http\Controllers\CaveController::class, 'index']);
Route::post('/caves', [App\Http\Controllers\CaveController::class, 'store'])->middleware(ApiIsAdmin::class);
Route::get('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'show']);
Route::put('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'update'])->middleware(ApiIsAdmin::class);

Route::get('/trips', [App\Http\Controllers\TripController::class, 'index']);
Route::post('/trips', [App\Http\Controllers\TripController::class, 'store']);
Route::get('/trips/{trip}', [App\Http\Controllers\TripController::class, 'show']);
Route::put('/trips/{trip}', [App\Http\Controllers\TripController::class, 'update']);
Route::delete('/trips/{trip}', [App\Http\Controllers\TripController::class, 'destroy']);

Route::get('/me/trips', [App\Http\Controllers\TripController::class, 'indexMe'])->middleware(ApiIsAuthenticated::class);

# Users
Route::get('/users', action: [App\Http\Controllers\UserController::class, 'index']);

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

Route::get('/tags', [App\Http\Controllers\TagsController::class, 'index']);


Route::get('/news', [App\Http\Controllers\NewsController::class, 'index']);

Route::get('/users/{user}', action: [App\Http\Controllers\UserController::class, 'show']);
Route::put('/users/{user}', action: [App\Http\Controllers\UserController::class, 'store']);

