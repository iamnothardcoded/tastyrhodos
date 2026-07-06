<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Jamasa\Core\Http\Controllers\OrderingSettingsController;

/*
 * Registered by Jamasa\Core\Extension::boot() inside the igniter-api middleware
 * group + prefix, so these resolve at:
 *   GET  /api/jamasa/ordering-settings
 *   POST /api/jamasa/ordering-settings
 * and require the same Sanctum bearer token as the rest of the TastyIgniter API.
 */
Route::get('jamasa/ordering-settings', [OrderingSettingsController::class, 'show']);
Route::post('jamasa/ordering-settings', [OrderingSettingsController::class, 'update']);
