<?php

use Illuminate\Support\Facades\Route;

// Vue Spa routing
Route::fallback(function () {
    return file_get_contents(public_path('index.html'));
});