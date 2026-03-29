<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\NotificationController;

// Force toutes les réponses en JSON
Route::middleware('api')->group(function () {

    Route::prefix('auth')->group(function () {

        // ── Public ──────────────────────────────────────────────────
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login',    [AuthController::class, 'login'])->name("login");
        Route::post('/refresh',  [AuthController::class, 'refresh']);

        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',  [AuthController::class, 'resetPassword'])
            ->name('password.reset');

        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->name('verification.verify')
            ->middleware('signed');

        // ── Authentifié ─────────────────────────────────────────────
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me',                   [AuthController::class, 'me']);
            Route::post('/logout',              [AuthController::class, 'logout']);
            Route::post('/logout-all',          [AuthController::class, 'logoutAll']);
            Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
        });
    });

    // ── Profil ───────────────────────────────────────────────────────
    Route::prefix('user')->middleware('auth:sanctum')->group(function () {
        Route::get('/profile',    [UserController::class, 'profile']);
        Route::put('/profile',    [UserController::class, 'update']);
        Route::post('/avatar',    [UserController::class, 'uploadAvatar']);
        Route::delete('/avatar',  [UserController::class, 'deleteAvatar']);
        Route::delete('/account', [UserController::class, 'deleteAccount']);
        Route::post('/restore/{id}',   [UserController::class, 'restoreAccount']);
    });

    // ── Notifications ────────────────────────────────────────────────
    Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
        Route::post('/send',      [NotificationController::class, 'send']);
        Route::post('/broadcast', [NotificationController::class, 'broadcast']);
    });
});
