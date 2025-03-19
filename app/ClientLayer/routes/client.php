<?php

use App\ClientLayer\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', ['profile']);
    Route::post('/profile/update', ['updateProfile']);
});
