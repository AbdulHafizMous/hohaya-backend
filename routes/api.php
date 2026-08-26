<?php

use App\Http\Controllers\Api\AbonnementController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChercheurController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FedaPayWebhookController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\PropertyMediaController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\SignalementController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {

    // ── Auth ─────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/register',        [AuthController::class, 'register']);
        Route::post('/login',           [AuthController::class, 'login'])->name('login');
        Route::post('/refresh',         [AuthController::class, 'refresh']);
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->name('password.reset');
        Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->name('verification.verify')
            ->middleware('signed');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me',                   [AuthController::class, 'me']);
            Route::post('/logout',              [AuthController::class, 'logout']);
            Route::post('/logout-all',          [AuthController::class, 'logoutAll']);
            Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
        });
    });

    // ── Profil utilisateur ───────────────────────────────────────────────────
    Route::prefix('user')->middleware('auth:sanctum')->group(function () {
        Route::get('/profile',       [UserController::class, 'profile']);
        Route::put('/profile',       [UserController::class, 'update']);
        Route::put('/password',      [UserController::class, 'updatePassword']);
        Route::post('/avatar',       [UserController::class, 'uploadAvatar']);
        Route::delete('/avatar',     [UserController::class, 'deleteAvatar']);
        Route::delete('/account',    [UserController::class, 'deleteAccount']);
        Route::post('/restore/{id}', [UserController::class, 'restoreAccount']);
    });

    // ── Annonces publiques ────────────────────────────────────────────────────
    Route::get('/properties',             [PropertyController::class, 'index']);
    Route::get('/properties/{id}',        [PropertyController::class, 'show'])->where('id', '[0-9]+');
    Route::get('/properties/{id}/medias', [PropertyMediaController::class, 'index'])->where('id', '[0-9]+');

    // ── Annonces authentifiées ────────────────────────────────────────────────
    Route::prefix('properties')->middleware('auth:sanctum')->group(function () {
        Route::get('/my', [PropertyController::class, 'myProperties']);

        // Publication — abonnement actif requis
        Route::middleware('abonnement.actif')->group(function () {
            Route::post('/', [PropertyController::class, 'store']);
        });

        Route::put('/{id}',          [PropertyController::class, 'update']);
        Route::delete('/{id}',       [PropertyController::class, 'destroy']);
        Route::patch('/{id}/status', [PropertyController::class, 'changeStatus']);
        Route::patch('/{id}/verify', [PropertyController::class, 'verify']);

        // Médias (upload, suppression, réordonnancement)
        Route::post('/{id}/medias',                     [PropertyMediaController::class, 'upload']);
        Route::get('/{id}/medias',                      [PropertyMediaController::class, 'index']);
        Route::delete('/{id}/medias/{mediaId}',         [PropertyMediaController::class, 'destroy']);
        Route::post('/{id}/medias/{mediaId}/principal', [PropertyMediaController::class, 'setPrincipal']);
        Route::post('/{id}/medias/reorder',             [PropertyMediaController::class, 'reorder']);
    });

    // ── Abonnements ───────────────────────────────────────────────────────────
    Route::get('/abonnements/tarifs', [AbonnementController::class, 'tarifs']);
    Route::prefix('abonnements')->middleware('auth:sanctum')->group(function () {
        Route::get('/actif',      [AbonnementController::class, 'actif']);
        Route::get('/historique', [AbonnementController::class, 'historique']);
        Route::post('/initier',   [AbonnementController::class, 'initier']);
        Route::post('/confirmer', [AbonnementController::class, 'confirmer']);
    });

    // ── Contacts (déblocage propriétaire) ─────────────────────────────────────
    Route::post('/contacts/guest-initier', [ContactController::class, 'guestInitier']);
    Route::prefix('contacts')->middleware('auth:sanctum')->group(function () {
        Route::post('/initier',   [ContactController::class, 'initier']);
        Route::post('/confirmer', [ContactController::class, 'confirmer']);
        Route::get('/historique', [ContactController::class, 'historique']);
    });

    // ── Réservations (paiement de l'avance) ─────────────────────────────────────
    Route::post('/reservations/guest-initier', [ReservationController::class, 'guestInitier']);
    Route::prefix('reservations')->middleware('auth:sanctum')->group(function () {
        Route::post('/initier',   [ReservationController::class, 'initier']);
        Route::post('/confirmer', [ReservationController::class, 'confirmer']);
    });

    // ── Paiements ─────────────────────────────────────────────────────────────
    Route::get('/paiements', [PaiementController::class, 'index'])->middleware('auth:sanctum');

    // ── Webhook FedaPay (sans auth — appelé directement par FedaPay) ──────────
    Route::post('/webhooks/fedapay', [FedaPayWebhookController::class, 'handle']);

    // ── Chercheur — favoris & visites ─────────────────────────────────────────
    Route::prefix('chercheur')->middleware('auth:sanctum')->group(function () {
        Route::post('/favoris/{propertyId}',  [ChercheurController::class, 'toggleFavori']);
        Route::get('/favoris',                [ChercheurController::class, 'mesFavoris']);
        Route::post('/visites',               [ChercheurController::class, 'demanderVisite']);
        Route::get('/visites',                [ChercheurController::class, 'mesVisites']);
        Route::patch('/visites/{id}/annuler', [ChercheurController::class, 'annulerVisite']);
    });

    // ── Propriétaire — gestion des visites reçues ─────────────────────────────
    Route::prefix('owner')->middleware('auth:sanctum')->group(function () {
        Route::get('/visites',                  [ChercheurController::class, 'visitesRecues']);
        Route::patch('/visites/{id}/repondre',  [ChercheurController::class, 'repondreVisite']);
        Route::get('/locataires',               [ReservationController::class, 'locatairesProprietaire']);
    });

    // ── Support tickets ───────────────────────────────────────────────────────
    Route::prefix('support')->middleware('auth:sanctum')->group(function () {
        Route::get('/tickets',               [SupportTicketController::class, 'index']);
        Route::post('/tickets',              [SupportTicketController::class, 'store']);
        Route::post('/tickets/{id}/respond', [SupportTicketController::class, 'respond']);
        Route::patch('/tickets/{id}/close',  [SupportTicketController::class, 'close']);
    });

    // ── Signalements ──────────────────────────────────────────────────────────
    Route::post('/signalements', [SignalementController::class, 'store'])->middleware('auth:sanctum');

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
        Route::post('/send',      [NotificationController::class, 'send']);
        Route::post('/broadcast', [NotificationController::class, 'broadcast']);
    });

    // ── Admin back-office ─────────────────────────────────────────────────────
    Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
        // Dashboard & stats
        Route::get('/dashboard',       [AdminController::class, 'dashboard']);
        Route::get('/revenus/mensuel', [AdminController::class, 'revenusMensuels']);

        // Gestion utilisateurs
        Route::get('/users',                    [AdminController::class, 'users']);
        Route::patch('/users/{id}/verify',      [AdminController::class, 'verifierProprietaire']);
        Route::patch('/users/{id}/suspension',  [AdminController::class, 'toggleSuspension']);
        Route::post('/users/{id}/restore',      [UserController::class, 'restoreAccount']);

        // Modération annonces
        Route::get('/properties/pending', [AdminController::class, 'annoncesEnAttente']);

        // Locataires
        Route::get('/locataires', [ReservationController::class, 'locatairesAdmin']);

        // Paiements
        Route::get('/paiements', [PaiementController::class, 'adminIndex']);

        // Support & signalements
        Route::get('/support/tickets',            [SupportTicketController::class, 'adminIndex']);
        Route::get('/signalements',               [SignalementController::class, 'adminIndex']);
        Route::patch('/signalements/{id}/treat',  [SignalementController::class, 'treat']);

        // Exports CSV
        Route::get('/export/paiements', [AdminController::class, 'exportPaiements']);
        Route::get('/export/users',     [AdminController::class, 'exportUsers']);

        // Gestion des tarifs d'abonnement
        Route::get('/tarifs',           [AdminController::class, 'tarifs']);
        Route::patch('/tarifs/{type}',  [AdminController::class, 'updateTarif']);

        // Paramètres généraux
        Route::get('/settings',                    [AdminController::class, 'settings']);
        Route::patch('/settings/deblocage-prix',    [AdminController::class, 'updateDeblocagePrix']);
    });
});
