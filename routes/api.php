<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardiovascularController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware([ForceJsonResponse::class, 'auth:api'])->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::prefix('cardiovascular')->group(function () {
        Route::get('/statistics', [CardiovascularController::class, 'statistics']);
        Route::get('/filter', [CardiovascularController::class, 'filter']);
        Route::get('/', [CardiovascularController::class, 'index']);
        Route::get('/{patientid}', [CardiovascularController::class, 'show']);
    });
});
