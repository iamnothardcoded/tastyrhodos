<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add the "Angenommen" order status (id 10) — the print queue in the famedo
 * order rail. An order is accepted (1 -> 10) by the backend auto-accept listener
 * (auto mode) or the Order Manager app (manual mode); the print-server watches
 * status 10, prints, and advances to 3. notify_customer = 0 so acceptance is
 * silent (the customer's "In Zubereitung" mail fires on 10 -> 3).
 *
 * Fixed id 10: ids 1-5/9 are order statuses, 6/7/8 are reservation statuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('statuses')->where('status_id', 10)->exists()) {
            return;
        }

        DB::table('statuses')->insert([
            'status_id' => 10,
            'status_name' => 'Angenommen',
            'status_comment' => null,
            'notify_customer' => 0,
            'status_for' => 'order',
            'status_color' => '#5FA9C4',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function down(): void
    {
        DB::table('statuses')
            ->where('status_id', 10)
            ->where('status_name', 'Angenommen')
            ->delete();
    }
};
