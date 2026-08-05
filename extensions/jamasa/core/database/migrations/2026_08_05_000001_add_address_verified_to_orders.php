<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `address_verified` — order-time truth for "did the geocoder confirm this
 * delivery address?", consumed by the delivery slip's Maps-QR gate (print
 * payload; the print-server stays dumb and only gates on it). Tri-state:
 *   NULL  = unknown (pre-feature order, API/POS-created, collection)
 *   1     = geocode-verified at placement
 *   0     = geocoder-blind rescue (fail-open) — QR suppressed on the slip,
 *           the encoded address TEXT would let Google fuzzy-match a random
 *           POI (the „Di An Di" incident, 2026-08-02).
 *
 * Lives on the ORDER, not the address row: address rows are shared/mutable
 * (saved-address edits would retroactively rewrite history), and the print
 * payload only carries order attributes. Stamped by the afterSaveOrder
 * listener in jamasa Extension::boot. No backfill on purpose — historical
 * orders stay NULL (fail-safe: no QR, no warning line).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'address_verified')) {
            Schema::table('orders', function($table): void {
                $table->boolean('address_verified')->nullable()->after('address_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'address_verified')) {
            Schema::table('orders', function($table): void {
                $table->dropColumn('address_verified');
            });
        }
    }
};
