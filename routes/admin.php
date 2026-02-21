<?php

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin routes for your application.
| These routes are loaded in bootstrap/app.php via the `then` callback
| and assigned to the 'api' middleware group with '/api' prefix.
|
*/

Route::prefix('admin')->as('admin.')->middleware(['auth:api', 'throttle:60,1'])->group(function () {
    // ---- @scaffold routes (do not remove) ----

    // Users
    Route::middleware('permission:users.read')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:users.update')->group(function () {
        Route::apiResource('users', UserController::class)->only(['update']);
    });
    Route::middleware('permission:users.toggleStatus')->group(function () {
        Route::middleware('throttle:10,1')->group(function () {
            Route::patch('users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');
        });
    });
    Route::middleware('permission:users.resetPassword')->group(function () {
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.resetPassword');
        });
    });

    // Roles
    Route::middleware('permission:roles.read')->group(function () {
        Route::apiResource('roles', RoleController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:roles.create')->group(function () {
        Route::apiResource('roles', RoleController::class)->only(['store']);
    });
    Route::middleware('permission:roles.update')->group(function () {
        Route::apiResource('roles', RoleController::class)->only(['update']);
    });
    Route::middleware('permission:roles.delete')->group(function () {
        Route::apiResource('roles', RoleController::class)->only(['destroy']);
    });

    // Permissions
    Route::middleware('permission:permissions.read')->group(function () {
        Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
    });

    // ---- End @scaffold routes ----
});
