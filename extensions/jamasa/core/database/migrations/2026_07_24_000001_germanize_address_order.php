<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Jamasa\Core\Helpers\AddressFormat;

/**
 * One-time sweep: normalize existing checkout-created address rows from
 * "2 Cottenburgstraße" (US-style) to German "Cottenburgstraße 2".
 * New rows are normalized on save (Address::extend beforeSave in jamasa
 * Extension.php); this migration converges the backlog. Naturally idempotent —
 * germanize() of an already-German string is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('addresses')->orderBy('address_id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $normalized = AddressFormat::germanize($row->address_1);
                if ($normalized !== $row->address_1) {
                    DB::table('addresses')->where('address_id', $row->address_id)
                        ->update(['address_1' => $normalized]);
                }
            }
        }, 'address_id');
    }

    public function down(): void
    {
        // irreversible by design — the US-style order carries no extra information
    }
};
