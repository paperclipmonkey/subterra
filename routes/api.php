<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Cave;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/caves', function (Request $request) {
    return Response::json(Cave::all());
});

Route::post('/caves', [App\Http\Controllers\CaveController::class, 'store']);

Route::get('/caves/{cave}', [App\Http\Controllers\CaveController::class, 'show']);


Route::post('/trips', [App\Http\Controllers\TripController::class, 'store']);

Route::get('/trips/{trip}', [App\Http\Controllers\TripController::class, 'show']);