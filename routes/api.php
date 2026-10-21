<?php

use App\Http\Controllers\CardiovascularController;
use Illuminate\Support\Facades\Route;

Route::prefix('cardiovascular')->group(function () {
    Route::get('/statistics', [CardiovascularController::class, 'statistics']);
    Route::get('/filter', [CardiovascularController::class, 'filter']);
    Route::get('/', [CardiovascularController::class, 'index']);
    Route::get('/{patientid}', [CardiovascularController::class, 'show']);
});
