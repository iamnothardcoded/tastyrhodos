<?php

declare(strict_types=1);

namespace Jamasa\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * famedo:checkout-report — are customers completing checkout?
 *
 * The cheap proxy alarm from Global TODO #1 (e)(2), built 2026-08-26. TI
 * creates a DRAFT order row (status_id = 0) the moment a customer first opens
 * checkout (OrderManager::loadOrder); completing the purchase flips that SAME
 * row to a real status. A draft still sitting at 0 therefore means "reached
 * checkout, never finished" — the 2026-08-08 El Greco failure looked exactly
 * like this and nobody was counting.
 *
 * ⚠️ A DRAFT IS AMBIGUOUS ON ITS OWN (recorded in the TODO): a PayPal redirect
 * that never returned, a mid-checkout login and a genuine give-up all leave the
 * identical row. The per-failure `famedo checkout rejected:` NOTICE lines (the
 * LogCheckoutRejections hook) are the real diagnosis; this command is the
 * dead-man's-style trend alarm that needs no log access.
 *
 * ⚠️ ALARM ON THE RATE, NOT THE EVENT (MailReport doctrine): some abandonment
 * is normal window-shopping. Gated by --floor so a quiet Tuesday morning with
 * three visitors is never an incident.
 *
 * ⚠️ Exit code is meaningful for a dead-man's-switch: 0 = healthy, 1 = alarm.
 * Do not route the alarm through e-mail alone — wire it to the push monitor.
 */
class CheckoutReport extends Command
{
    protected $signature = 'famedo:checkout-report
        {--hours=24 : Window to judge, in hours}
        {--max-draft-ratio=80 : Alarm when drafts exceed this percent of checkout sessions (drafts + completed)}
        {--floor=10 : Ignore the ratio until at least this many checkout sessions exist in the window}
        {--json : Machine-readable output}';

    protected $description = 'Report checkout completion vs abandoned drafts for this tenant and exit non-zero on alarm';

    public function handle(): int
    {
        if (!Schema::hasTable('orders')) {
            $this->warn('orders table missing — run: php artisan igniter:up --force');

            return self::FAILURE;
        }

        $hours = max(1, (int) $this->option('hours'));
        $since = now()->subHours($hours);

        // Draft = status_id 0 (or NULL defensively — OwnerController convention).
        $drafts = DB::table('orders')
            ->where('created_at', '>=', $since)
            ->where(fn($q) => $q->where('status_id', 0)->orWhereNull('status_id'))
            ->count();

        // Real = everything that entered the order flow; 9 = storniert stays out
        // of "completed" but is NOT a checkout failure, so count it separately.
        $completed = DB::table('orders')
            ->where('created_at', '>=', $since)
            ->whereNotNull('status_id')
            ->whereNotIn('status_id', [0, 9])
            ->count();

        $canceled = DB::table('orders')
            ->where('created_at', '>=', $since)
            ->where('status_id', 9)
            ->count();

        $sessions = $drafts + $completed;
        $ratio = $sessions > 0 ? (int) round($drafts / $sessions * 100) : null;

        $alarms = $this->evaluate($drafts, $completed, $sessions, $ratio);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'window_hours' => $hours,
                'drafts' => $drafts, 'completed' => $completed, 'canceled' => $canceled,
                'draft_ratio' => $ratio, 'alarms' => $alarms,
            ]));
        } else {
            $this->line(sprintf(
                'last %dh: completed=%d drafts=%d canceled=%d abandonment=%s',
                $hours, $completed, $drafts, $canceled, $ratio === null ? 'n/a' : $ratio.'%'
            ));

            foreach ($this->draftBreakdown($since) as $row) {
                $this->line(sprintf('  drafts %-12s %s', $row->order_type ?? 'unknown', $row->n.'×'));
            }
        }

        foreach ($alarms as $alarm) {
            $this->error('  ⚠ '.$alarm);
            // Logged too: the console output of a cron job is nobody's monitoring.
            Log::warning('famedo checkout-report ALARM: '.$alarm);
        }

        return $alarms === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> */
    protected function evaluate(int $drafts, int $completed, int $sessions, ?int $ratio): array
    {
        $alarms = [];
        $floor = max(1, (int) $this->option('floor'));
        $maxRatio = max(1, (int) $this->option('max-draft-ratio'));

        // (1) Total blocker: checkouts are being opened but NOT ONE completes.
        // This is what the 2026-08-02..09 class of outage looks like from the DB.
        if ($completed === 0 && $drafts >= $floor) {
            $alarms[] = sprintf('%d checkout sessions, ZERO completed orders — the checkout may be broken', $drafts);
        } elseif ($ratio !== null && $sessions >= $floor && $ratio > $maxRatio) {
            // (2) The trend rule — abandonment above the tolerated ceiling.
            $alarms[] = sprintf('abandonment %d%% is above %d%% (%d drafts / %d sessions)', $ratio, $maxRatio, $drafts, $sessions);
        }

        return $alarms;
    }

    protected function draftBreakdown(\DateTimeInterface $since)
    {
        return DB::table('orders')
            ->where('created_at', '>=', $since)
            ->where(fn($q) => $q->where('status_id', 0)->orWhereNull('status_id'))
            ->groupBy('order_type')
            ->selectRaw('order_type, count(*) as n')
            ->orderByDesc('n')
            ->get();
    }
}
