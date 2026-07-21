<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Jamasa\Core\Http\Controllers\OrderingSettingsController;
use Jamasa\Core\Http\Controllers\OwnerController;
use Jamasa\Core\Http\Middleware\EnsureOwner;

/*
 * Registered by Jamasa\Core\Extension::boot() inside the igniter-api middleware
 * group + prefix, so these resolve at:
 *   GET  /api/jamasa/ordering-settings
 *   POST /api/jamasa/ordering-settings
 * and require the same Sanctum bearer token as the rest of the TastyIgniter API.
 *
 * Consumed by BOTH the print-server / KDS (wildcard `['*']` tokens) and the
 * owner console (`['owner']` tokens) — pause, lead-time and manual-accept live
 * here and are equally valid for either caller, so no EnsureOwner gate.
 */
Route::get('jamasa/ordering-settings', [OrderingSettingsController::class, 'show']);
Route::post('jamasa/ordering-settings', [OrderingSettingsController::class, 'update']);

/*
 * Owner console API — owner-scoped surface (sold-out toggles, customer export,
 * revenue/menu overview). EnsureOwner requires the `owner` ability; the
 * complementary ConfineOwnerToken (appended to the `api` group in boot) stops an
 * owner token reaching anything outside api/jamasa/*.
 */
Route::middleware(EnsureOwner::class)->group(function (): void {
    Route::get('jamasa/owner/overview', [OwnerController::class, 'overview']);
    Route::post('jamasa/owner/menu-status', [OwnerController::class, 'menuStatus']);
    Route::post('jamasa/owner/category-status', [OwnerController::class, 'categoryStatus']);
    Route::get('jamasa/owner/customers', [OwnerController::class, 'customers']);
    Route::get('jamasa/owner/customers.csv', [OwnerController::class, 'customersCsv']);
});
