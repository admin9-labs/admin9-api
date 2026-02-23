<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)
    ->middleware('throttle:30,1')
    ->name('health');

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
    Route::get('/me/menu', [AuthController::class, 'menu'])->name('auth.me.menu');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('auth.login');
    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:10,1')
        ->name('auth.refresh');
});
