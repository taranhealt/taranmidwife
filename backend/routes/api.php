<?php
use App\Http\Controllers\Api\AdminPartnerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::prefix('admin')->middleware(EnsureRole::class . ':reviewer,sysadmin')->group(function () {
            Route::get('partners', [AdminPartnerController::class, 'index']);
        });
    });
});
