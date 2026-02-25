<?php

use App\Http\Controllers\System\AuditLogController;
use App\Http\Controllers\System\DictionaryItemController;
use App\Http\Controllers\System\DictionaryTypeController;
use App\Http\Controllers\System\MenuController;
use App\Http\Controllers\System\RoleController;
use App\Http\Controllers\System\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System Routes
|--------------------------------------------------------------------------
|
| Here is where you can register system routes for your application.
| These routes are loaded in bootstrap/app.php via the `then` callback
| and assigned to the 'api' middleware group with '/api' prefix.
|
*/

Route::prefix('system')->as('system.')->middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::whereNumber(['user', 'role', 'menu', 'dict_type', 'dict_item', 'audit_log']);

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
    Route::middleware('permission:users.assignRoles')->group(function () {
        Route::put('users/{user}/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');
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

    // Permissions management has moved into Menu tree.

    // Menus
    Route::middleware('permission:menus.read')->group(function () {
        Route::apiResource('menus', MenuController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:menus.create')->group(function () {
        Route::apiResource('menus', MenuController::class)->only(['store']);
    });
    Route::middleware('permission:menus.update')->group(function () {
        Route::apiResource('menus', MenuController::class)->only(['update']);
    });
    Route::middleware('permission:menus.delete')->group(function () {
        Route::apiResource('menus', MenuController::class)->only(['destroy']);
    });

    // Dictionary Types
    Route::middleware('permission:dictTypes.read')->group(function () {
        Route::get('dict-types/{code}/items', [DictionaryTypeController::class, 'items'])->where('code', '[a-zA-Z0-9_-]+')->name('dict-types.items');
        Route::apiResource('dict-types', DictionaryTypeController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:dictTypes.create')->group(function () {
        Route::apiResource('dict-types', DictionaryTypeController::class)->only(['store']);
    });
    Route::middleware('permission:dictTypes.update')->group(function () {
        Route::apiResource('dict-types', DictionaryTypeController::class)->only(['update']);
    });
    Route::middleware('permission:dictTypes.delete')->group(function () {
        Route::apiResource('dict-types', DictionaryTypeController::class)->only(['destroy']);
    });

    // Dictionary Items
    Route::middleware('permission:dictItems.read')->group(function () {
        Route::apiResource('dict-items', DictionaryItemController::class)->only(['index', 'show']);
    });
    Route::middleware('permission:dictItems.create')->group(function () {
        Route::apiResource('dict-items', DictionaryItemController::class)->only(['store']);
    });
    Route::middleware('permission:dictItems.update')->group(function () {
        Route::apiResource('dict-items', DictionaryItemController::class)->only(['update']);
    });
    Route::middleware('permission:dictItems.delete')->group(function () {
        Route::apiResource('dict-items', DictionaryItemController::class)->only(['destroy']);
    });

    // Audit Logs
    Route::middleware('permission:auditLogs.read')->group(function () {
        Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'show']);
    });

    // ---- End @scaffold routes ----
});
