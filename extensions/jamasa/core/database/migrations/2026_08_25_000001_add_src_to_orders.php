<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `src` — channel attribution: which published surface (?src=<token>) the
 * ordering session arrived from (maps, flyer-2608, website-maps, tuete, …).
 * NULL = untagged arrival, API/POS-created order, or pre-feature order.
 *
 * Captured on the first request by CaptureChannelSource (session stash,
 * last touch wins), stamped at placement by the afterSaveOrder listener in
 * jamasa Extension::boot. Lives on the ORDER because visits only prove a
 * surface got scanned — orders prove it paid for itself. No backfill:
 * historical orders predate capture and stay NULL honestly.
 *
 * 32 chars matches the middleware's validation cap (longest live token
 * today: website-<origin> ≤ 28). Indexed for the future analytics
 * consumer's group-by-src queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'src')) {
            Schema::table('orders', function($table): void {
                $table->string('src', 32)->nullable()->index()->after('address_verified');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'src')) {
            Schema::table('orders', function($table): void {
                $table->dropColumn('src');
            });
        }
    }
};
