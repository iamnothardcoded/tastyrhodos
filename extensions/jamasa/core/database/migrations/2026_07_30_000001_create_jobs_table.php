<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `jobs` — backing table for QUEUE_CONNECTION=database. TI queues every
 * template mail (MailHelper::queueTemplate), but the fleet ran queue=sync,
 * which executes the SMTP send INLINE in the customer's checkout request —
 * a hung relay would freeze checkout for the socket timeout. Real SMTP
 * (transactional-email work, 2026-07-30) therefore requires an async queue.
 *
 * Database driver on purpose, NOT shared-redis: every tenant shares one
 * Redis with identical key prefixes, so queues would collide cross-tenant
 * and a worker could resolve another tenant's order IDs against its own DB.
 * The jobs table lives in each tenant's own database — isolation for free.
 *
 * Schema = stock Laravel queue table. failed_jobs already exists (core).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs')) {
            return;
        }

        Schema::create('jobs', function(Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
