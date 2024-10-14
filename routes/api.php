<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/caves', [App\Http\Controllers\CaveController::class, 'index']);
Route::post('/caves', [App\Http\Controllers\CaveController::class, 'store']);
Route::get('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'show']);

Route::get('/trips', [App\Http\Controllers\TripController::class, 'index']);
Route::post('/trips', [App\Http\Controllers\TripController::class, 'store']);
Route::get('/trips/{trip}', [App\Http\Controllers\TripController::class, 'show']);
Route::delete('/trips/{trip}', [App\Http\Controllers\TripController::class, 'destroy']);

Route::get('/me/trips', [App\Http\Controllers\TripController::class, 'indexMe']);

# Users
Route::get('/users', action: [App\Http\Controllers\UserController::class, 'index']);

Route::get('/users/me', function (Request $request) {
    if($request->user()) {
        return Response::json(["data"=> $request->user()]);
    } else {
        return abort(400, 'No user logged in');
    }
});

Route::get('/users/{user}', action: [App\Http\Controllers\UserController::class, 'show']);
