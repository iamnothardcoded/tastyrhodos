<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mail delivery visibility — the two tables behind "never again".
 *
 * WHY TWO TABLES AND NOT ONE. From 2026-08-02 to 2026-08-09 every customer
 * order-confirmation was rejected by the ESP and NOTHING on our side knew:
 * Brevo answers 250 OK at SMTP and rejects internally afterwards, so the queue
 * drained, failed_jobs stayed 0 and laravel.log was clean. It surfaced only
 * because the owner exported the ESP log by hand.
 *
 * An event table ALONE cannot fix that, because it can never distinguish
 * "no bad news" from "the feed is dead" — which is precisely this week's
 * failure mode. So we record BOTH sides:
 *   mail_messages — what WE handed to the ESP (one row per outgoing mail)
 *   mail_events   — what the ESP said afterwards (append-only, 0..n per message)
 * A message with no terminal event after N minutes is then a first-class,
 * detectable state rather than silence.
 *
 * ⚠️ DSGVO / data minimisation. The recipient address is customer PII that
 * already lives lawfully in `orders`/`customers`; a second copy here would be a
 * new processing purpose and a new deletion obligation. We therefore store a
 * one-way `recipient_hash` (matching + dedupe) plus the bare `recipient_domain`
 * (gmx/gmail/web.de failure patterns are the operational signal, and a domain is
 * not personal data). Raw ESP payloads are kept ONLY for failures and only after
 * the address is stripped. See .knowledge/MAIL-ARCHITECTURE.md §3.
 *
 * ⚠️ Tenant ownership is decided by message_id, NOT by sender: ONE Brevo account
 * serves the whole fleet, its webhook payload carries no sender field at all, and
 * new tenants are born sharing `noreply-order@famedo.app` (new-tenant.sh) — so
 * sender-based routing is not merely awkward, it is wrong. An event whose
 * message_id is absent from this tenant's mail_messages belongs to a sibling
 * tenant and is discarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mail_messages')) {
            Schema::create('mail_messages', function(Blueprint $table): void {
                $table->increments('id');

                // The ESP's join key. Symfony mints it from the sender domain, and
                // it is echoed back in every Brevo event payload.
                $table->string('message_id', 191)->unique();

                // What this mail WAS, so an alarm can weigh it: a failed order
                // confirmation or login code is an incident; a failed marketing
                // blast is not. Free-form (mail template code) rather than an enum
                // so a new template never needs a migration.
                $table->string('mail_type', 64)->nullable()->index();

                // Whose identity we sent AS. Kept for forensics: the whole outage
                // was a wrong value in exactly this field.
                $table->string('from_address', 191)->nullable();

                // ⚠️ NOT the address — see the DSGVO note above.
                $table->char('recipient_hash', 64)->index();
                $table->string('recipient_domain', 128)->nullable()->index();

                // Denormalised terminal state, maintained by the ingester so the
                // hot query ("how many of the last N failed?") needs no join.
                // null = handed over, nothing heard back yet.
                $table->string('status', 32)->nullable()->index();
                $table->timestamp('status_at')->nullable();

                $table->timestamp('sent_at')->index();
            });
        }

        if (!Schema::hasTable('mail_events')) {
            Schema::create('mail_events', function(Blueprint $table): void {
                $table->increments('id');

                // Deliberately NOT a foreign key: events can arrive for a message
                // we never recorded (a send from before this feature shipped), and
                // losing the event would repeat the very bug we are fixing.
                $table->string('message_id', 191)->index();

                // ⚠️ Brevo sends snake_case (`hard_bounce`) but you SUBSCRIBE in
                // camelCase (`hardBounce`) — do not "normalise" one into the other.
                $table->string('event', 32)->index();

                // The ESP's own words. This is the sentence that would have ended
                // the outage on day one: "the sender you used … is not valid".
                $table->text('reason')->nullable();

                // Failures only, address stripped. A delivered event tells us
                // nothing worth storing beyond the fact itself.
                $table->json('raw')->nullable();

                $table->timestamp('occurred_at')->index();
                $table->timestamp('created_at')->nullable();

                // The ESP retries, and a retry must not double-count a failure.
                $table->unique(['message_id', 'event', 'occurred_at'], 'mail_events_dedupe');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_events');
        Schema::dropIfExists('mail_messages');
    }
};
