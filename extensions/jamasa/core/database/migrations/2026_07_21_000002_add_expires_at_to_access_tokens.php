<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a nullable `expires_at` to the API access tokens table.
 *
 * TI's tokens table (ti-ext-api, migration dated 2020) predates Laravel
 * Sanctum's per-token expiry column, so tokens never expire. The owner console
 * needs short-lived session tokens (re-login after N hours). Sanctum's guard
 * already honours `expires_at` when the column exists — adding it here makes
 * per-token expiry work with no other changes. Existing tokens (print-server,
 * KDS) get NULL = never expire, so they are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('igniter_api_access_tokens', 'expires_at')) {
            return;
        }

        Schema::table('igniter_api_access_tokens', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('igniter_api_access_tokens', 'expires_at')) {
            return;
        }

        Schema::table('igniter_api_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
