<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\SignalementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PropertyMediaController;

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


    // Annonces publiques
    Route::get('/properties',      [PropertyController::class, 'index']);
    Route::get('/properties/{id}', [PropertyController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/properties/{id}/medias', [PropertyMediaController::class, 'index'])->where('id', '[0-9]+');

    // Annonces authentifiées
    Route::prefix('properties')->middleware('auth:sanctum')->group(function () {
        Route::get('/my',                    [PropertyController::class, 'myProperties']);
        Route::post('/',                     [PropertyController::class, 'store']);        // sans images
        Route::put('/{id}',                  [PropertyController::class, 'update']);
        Route::delete('/{id}',               [PropertyController::class, 'destroy']);
        Route::patch('/{id}/status',         [PropertyController::class, 'changeStatus']);
        Route::patch('/{id}/verify',         [PropertyController::class, 'verify']);

        // Médias — une seule route pour upload (création ET ajout)
        Route::post('/{id}/medias',                      [PropertyMediaController::class, 'upload']);
        Route::delete('/{id}/medias/{mediaId}',          [PropertyMediaController::class, 'destroy']);
        Route::post('/{id}/medias/{mediaId}/principal',  [PropertyMediaController::class, 'setPrincipal']);
        Route::post('/{id}/medias/reorder',              [PropertyMediaController::class, 'reorder']);
    });

    // ── Support tickets ───────────────────────────────────────────────────────────
    Route::prefix('support')->middleware('auth:sanctum')->group(function () {
        Route::get('/tickets',              [SupportTicketController::class, 'index']);
        Route::post('/tickets',             [SupportTicketController::class, 'store']);
        Route::post('/tickets/{id}/respond', [SupportTicketController::class, 'respond']);
        Route::patch('/tickets/{id}/close', [SupportTicketController::class, 'close']);
    });

    // ── Admin support & signalements ──────────────────────────────────────────────
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        Route::get('/support/tickets',              [SupportTicketController::class, 'adminIndex']);
        Route::get('/signalements',                 [SignalementController::class, 'adminIndex']);
        Route::patch('/signalements/{id}/treat',    [SignalementController::class, 'treat']);
    });

    // ── Signalements (utilisateurs) ───────────────────────────────────────────────
    Route::post('/signalements', [SignalementController::class, 'store'])
        ->middleware('auth:sanctum');

    // ── Notifications ────────────────────────────────────────────────
    Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
        Route::post('/send',      [NotificationController::class, 'send']);
        Route::post('/broadcast', [NotificationController::class, 'broadcast']);
    });
});
