<?php

declare(strict_types=1);

namespace Jamasa\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * famedo:mail-report — is our mail actually arriving?
 *
 * The question nobody could answer from 2026-08-02 to 2026-08-09, while every
 * customer order-confirmation was being rejected by the ESP. Our own stack said
 * "fine" the whole time (queue drained, failed_jobs 0, clean log) because Brevo
 * accepts at SMTP and rejects internally afterwards.
 *
 * Reads the two tables filled by the MessageSent listener (what we sent) and
 * MailEventsController (what the ESP said). See .knowledge/MAIL-ARCHITECTURE.md.
 *
 * ⚠️ ALARM ON THE RATE, NOT ON THE EVENT. A mail per failure would be noise and
 * would burn the send quota that gates passwordless LOGIN — i.e. the alarm could
 * lock customers out of their accounts. During the real outage the failure rate
 * was 100%, so a rate rule catches it on day one and stays quiet for the ordinary
 * customer typo.
 *
 * ⚠️ THE "PENDING" NUMBER IS THE POINT. An event table alone cannot tell
 * "no bad news" from "the feed is dead" — a message with no terminal verdict is
 * the only way to detect that the webhook itself stopped, which is the same class
 * of blindness we are fixing. Treat a rising pending count as an incident.
 *
 * ⚠️ Exit code is meaningful so a dead-man's-switch can hang off it:
 * 0 = healthy (ping the uptime monitor), 1 = alarm. Do NOT route the alarm itself
 * through e-mail only — if mail is broken, the alarm is broken. Use the push
 * monitor (Uptime Kuma) as the out-of-band channel.
 */
class MailReport extends Command
{
    protected $signature = 'famedo:mail-report
        {--hours=24 : Window to judge, in hours}
        {--min-rate=80 : Alarm when the delivered ratio drops below this percent}
        {--floor=10 : Ignore the ratio until at least this many mails were sent}
        {--pending-after=30 : A mail with no ESP verdict after this many minutes counts as pending}
        {--json : Machine-readable output}';

    protected $description = 'Report ESP delivery health for this tenant and exit non-zero on alarm';

    public function handle(): int
    {
        if (!Schema::hasTable('mail_messages') || !Schema::hasTable('mail_events')) {
            $this->warn('mail_messages/mail_events missing — run: php artisan igniter:up --force');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $since = now()->subHours($hours);

        $rows = DB::table('mail_messages')->where('sent_at', '>=', $since)->get();
        $sent = $rows->count();

        $delivered = $rows->where('status', 'delivered')->count();
        $failed = $rows->whereIn('status', ['hard_bounce', 'blocked', 'invalid_email', 'error', 'spam'])->count();

        // No verdict yet AND old enough that one should have arrived.
        $pendingCutoff = now()->subMinutes(max(1, (int) $this->option('pending-after')));
        $pending = $rows->filter(
            fn($r): bool => $r->status === null && $r->sent_at !== null && $r->sent_at < $pendingCutoff->format('Y-m-d H:i:s')
        )->count();

        $judged = $delivered + $failed;
        $rate = $judged > 0 ? (int) round($delivered / $judged * 100) : null;

        $alarms = $this->evaluate($sent, $delivered, $failed, $pending, $rate);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'window_hours' => $hours,
                'sent' => $sent, 'delivered' => $delivered, 'failed' => $failed,
                'pending' => $pending, 'delivered_rate' => $rate, 'alarms' => $alarms,
            ]));
        } else {
            $this->line(sprintf(
                'last %dh: sent=%d delivered=%d failed=%d pending=%d rate=%s',
                $hours, $sent, $delivered, $failed, $pending, $rate === null ? 'n/a' : $rate.'%'
            ));

            foreach ($this->failureBreakdown($since) as $row) {
                $this->line(sprintf('  %-14s %-22s %s', $row->event, $row->mail_type ?? 'unknown', $row->n.'×'));
            }
        }

        foreach ($alarms as $alarm) {
            $this->error('  ⚠ '.$alarm);
            // Logged too: the console output of a cron job is nobody's monitoring.
            Log::warning('famedo mail-report ALARM: '.$alarm);
        }

        return $alarms === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    protected function evaluate(int $sent, int $delivered, int $failed, int $pending, ?int $rate): array
    {
        $alarms = [];
        $floor = max(1, (int) $this->option('floor'));
        $minRate = max(1, (int) $this->option('min-rate'));

        // (1) The rate rule — would have fired on day one of the real outage (0%).
        // Gated by a floor so three mails and one typo is not an incident.
        if ($rate !== null && ($delivered + $failed) >= $floor && $rate < $minRate) {
            $alarms[] = sprintf('delivery rate %d%% is below %d%% (%d delivered / %d judged)', $rate, $minRate, $delivered, $delivered + $failed);
        }

        // (2) Nothing judged at all while mail was sent ⇒ either the ESP stopped
        // answering or our webhook is dead. Indistinguishable from here, and both
        // are incidents — this is the check that catches a silent feed.
        if ($sent > 0 && $pending === $sent) {
            $alarms[] = sprintf('%d mails sent, NOT ONE verdict from the ESP — webhook or feed is dead', $sent);
        } elseif ($pending > 0 && $pending >= (int) ceil($sent / 2) && $sent >= $floor) {
            $alarms[] = sprintf('%d of %d mails still have no ESP verdict', $pending, $sent);
        }

        // (3) Any outright rejection is worth naming even below the rate floor:
        // `blocked`/`error` are OUR fault (bad sender, bad config), unlike a
        // hard_bounce, which is usually the customer's typo.
        $ours = DB::table('mail_events')
            ->whereIn('event', ['blocked', 'error'])
            ->where('occurred_at', '>=', now()->subHours(max(1, (int) $this->option('hours'))))
            ->count();
        if ($ours > 0) {
            $alarms[] = sprintf('%d mail(s) BLOCKED/ERROR by the ESP — this is a sender/config fault, not a bad address', $ours);
        }

        return $alarms;
    }

    protected function failureBreakdown(\DateTimeInterface $since)
    {
        return DB::table('mail_events')
            ->leftJoin('mail_messages', 'mail_messages.message_id', '=', 'mail_events.message_id')
            ->where('mail_events.occurred_at', '>=', $since)
            ->whereIn('mail_events.event', ['hard_bounce', 'soft_bounce', 'blocked', 'invalid_email', 'error', 'spam'])
            ->groupBy('mail_events.event', 'mail_messages.mail_type')
            ->selectRaw('mail_events.event, mail_messages.mail_type, count(*) as n')
            ->orderByDesc('n')
            ->get();
    }
}
