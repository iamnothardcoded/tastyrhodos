<?php

declare(strict_types=1);

use Igniter\User\Models\Customer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jamasa\Core\Helpers\EmailNormalizer;

/**
 * `normalized_email` — the canonical account identity for the passwordless
 * email-code flow. `email` stays the address the customer typed (for display +
 * delivery); identity lookups/creation key on the normalized form so gmail
 * dot/+tag variants resolve to ONE account (welcome-discount farming shield).
 *
 * Kept in step by a Customer model.beforeSave hook (jamasa Extension::boot).
 * Non-unique index for now: variant duplicates could already exist from before
 * the shield; a unique constraint waits until a dedup pass (see TODO). The
 * backfill logs any pre-existing collisions so they can be merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'normalized_email')) {
            Schema::table('customers', function($table): void {
                $table->string('normalized_email', 96)->nullable()->after('email');
                $table->index('normalized_email');
            });
        }

        $seen = [];
        Customer::query()->select('customer_id', 'email')->orderBy('customer_id')
            ->chunkById(500, function($customers) use (&$seen): void {
                foreach ($customers as $customer) {
                    if (!filled($customer->email)) {
                        continue;
                    }
                    $normalized = EmailNormalizer::normalize((string)$customer->email);
                    if (isset($seen[$normalized])) {
                        logger()->warning('normalized_email collision: customers #'
                            .$seen[$normalized].' and #'.$customer->customer_id.' both → '.$normalized);
                    } else {
                        $seen[$normalized] = $customer->customer_id;
                    }
                    Customer::query()->whereKey($customer->customer_id)
                        ->update(['normalized_email' => $normalized]);
                }
            }, 'customer_id');
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'normalized_email')) {
            Schema::table('customers', function($table): void {
                $table->dropIndex(['normalized_email']);
                $table->dropColumn('normalized_email');
            });
        }
    }
};
