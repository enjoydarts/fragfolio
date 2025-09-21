<?php

use App\Http\Controllers\Api\Admin\AdminBrandController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminFragranceController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\WebAuthnController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\FragranceController;
use App\Http\Controllers\Api\FragranceNormalizationController;
use App\Http\Controllers\Api\TurnstileController;
use App\Http\Controllers\Api\UserFragranceController;
use App\Http\Controllers\Api\WearingLogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Email verification route
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmailFromLink'])
        ->middleware(['signed'])
        ->name('verification.verify');

    // Password reset route

    // WebAuthn routes
    Route::prefix('webauthn')->group(function () {
        Route::post('/register/begin', [WebAuthnController::class, 'registerBegin']);
        Route::post('/register/complete', [WebAuthnController::class, 'registerComplete']);
        Route::post('/authenticate/begin', [WebAuthnController::class, 'authenticateBegin']);
        Route::post('/authenticate/complete', [WebAuthnController::class, 'authenticateComplete']);
    });
});

// Public data routes
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/brands/{brand}', [BrandController::class, 'show']);
Route::get('/brands/{brand}/fragrances', [BrandController::class, 'fragrances']);
Route::get('/fragrances', [FragranceController::class, 'index']);
Route::get('/fragrances/{fragrance}', [FragranceController::class, 'show']);
Route::get('/fragrances/search', [FragranceController::class, 'search']);

// Turnstile configuration
Route::get('/turnstile/config', [TurnstileController::class, 'config']);

// AI normalization routes (public for auto-completion)
Route::prefix('ai')->group(function () {
    Route::post('/normalize', [FragranceNormalizationController::class, 'normalize']);
    Route::get('/providers', [FragranceNormalizationController::class, 'getAvailableProviders']);
    Route::post('/normalize/brand', [AIController::class, 'normalizeBrand']);
    Route::post('/normalize/fragrance', [AIController::class, 'normalizeFragrance']);
    Route::post('/normalize/note', [AIController::class, 'normalizeNote']);
    Route::post('/suggest/fragrance-data', [AIController::class, 'suggestFragranceData']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User routes
    Route::get('/user', function (Request $request) {
        return $request->user()->load('profile');
    });

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
        Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail']);
    });

    // User fragrance collection
    Route::prefix('my')->group(function () {
        Route::apiResource('fragrances', UserFragranceController::class);
        Route::get('fragrances/{userFragrance}/wearing-logs', [UserFragranceController::class, 'wearingLogs']);
        Route::post('fragrances/{userFragrance}/rate', [UserFragranceController::class, 'rate']);
        Route::post('fragrances/{userFragrance}/tags', [UserFragranceController::class, 'addTag']);
        Route::delete('fragrances/{userFragrance}/tags/{tag}', [UserFragranceController::class, 'removeTag']);
    });

    // Wearing logs
    Route::apiResource('wearing-logs', WearingLogController::class);
    Route::get('wearing-logs/{wearingLog}/reactions', [WearingLogController::class, 'reactions']);
    Route::post('wearing-logs/{wearingLog}/reactions', [WearingLogController::class, 'addReaction']);

    // Statistics
    Route::prefix('stats')->group(function () {
        Route::get('/overview', [UserFragranceController::class, 'stats']);
        Route::get('/wearing-patterns', [WearingLogController::class, 'patterns']);
        Route::get('/favorite-brands', [UserFragranceController::class, 'favoriteBrands']);
        Route::get('/favorite-notes', [UserFragranceController::class, 'favoriteNotes']);
    });

    // AI normalization history
    Route::get('/ai/history', [FragranceNormalizationController::class, 'getNormalizationHistory']);

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/statistics', [AdminController::class, 'statistics']);

        // Admin brand management
        Route::apiResource('brands', AdminBrandController::class);
        Route::post('brands/{brand}/toggle-status', [AdminBrandController::class, 'toggleStatus']);

        // Admin fragrance management
        Route::apiResource('fragrances', AdminFragranceController::class);
        Route::post('fragrances/{fragrance}/toggle-status', [AdminFragranceController::class, 'toggleStatus']);
        Route::post('fragrances/{fragrance}/categories', [AdminFragranceController::class, 'attachCategories']);
        Route::post('fragrances/{fragrance}/notes', [AdminFragranceController::class, 'attachNotes']);
        Route::post('fragrances/{fragrance}/scenes', [AdminFragranceController::class, 'attachScenes']);
        Route::post('fragrances/{fragrance}/seasons', [AdminFragranceController::class, 'attachSeasons']);

        // Master data management
        Route::get('/concentration-types', [AdminController::class, 'concentrationTypes']);
        Route::get('/fragrance-categories', [AdminController::class, 'fragranceCategories']);
        Route::get('/fragrance-notes', [AdminController::class, 'fragranceNotes']);
        Route::get('/scenes', [AdminController::class, 'scenes']);
        Route::get('/seasons', [AdminController::class, 'seasons']);

        // AI logs
        Route::get('/ai-logs', [AdminController::class, 'aiLogs']);
        Route::get('/ai-logs/{log}', [AdminController::class, 'aiLogDetails']);
    });
});
