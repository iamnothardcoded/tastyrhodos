<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brevo transactional-webhook sink — the ear we did not have.
 *
 * Background: every customer order-confirmation was rejected by the ESP for a
 * week with zero signal on our side (Brevo accepts at SMTP with 250 OK and
 * rejects internally afterwards). This endpoint is the channel that carries the
 * bad news back. See .knowledge/MAIL-ARCHITECTURE.md §3.
 *
 * ⚠️⚠️ THE RULE THAT SHAPES THIS WHOLE CLASS — BREVO DOES NOT RETRY ON ERRORS.
 * Per Brevo's docs, any 4xx (except 429) and ANY 5xx "stop all retry attempts and
 * discard the webhook". A single uncaught exception therefore destroys the event
 * FOREVER — i.e. an ordinary bug here silently recreates the exact blindness this
 * feature exists to remove. Consequences, deliberately:
 *   1. Everything after the auth check is wrapped in catch(Throwable) and still
 *      answers 200. We would rather log a parse failure than lose the event.
 *   2. We persist first and interpret second.
 *   3. An event for another tenant is a 200, not a 4xx (see ownership below).
 *
 * OWNERSHIP — one Brevo ACCOUNT serves the whole fleet, so every tenant's webhook
 * receives every tenant's events. Brevo cannot filter a transactional webhook by
 * sender, and the payload carries no sender field at all. We therefore decide by
 * LOOKUP: if the message_id is not in OUR mail_messages, the mail was not ours.
 * ⚠️ Do NOT be tempted to route on the From address instead — fresh tenants are
 * born sharing `noreply-order@famedo.app` (new-tenant.sh), so sender routing is
 * ambiguous by construction.
 *
 * AUTH — Brevo does not sign webhooks (no HMAC, no signature header). All that is
 * on offer is a bearer token / basic auth / IP allowlist, so the token below IS
 * the security boundary and the URL must be treated as a secret.
 */
class MailEventsController
{
    /** Events that mean the mail did NOT reach the human. */
    private const FAILURE_EVENTS = [
        'hard_bounce', 'soft_bounce', 'blocked', 'invalid_email', 'error', 'spam', 'deferred',
    ];

    /** Terminal states — later events must not overwrite a stronger verdict. */
    private const TERMINAL_EVENTS = [
        'delivered', 'hard_bounce', 'blocked', 'invalid_email', 'error', 'spam',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('jamasa.core.mailWebhookToken', env('BREVO_WEBHOOK_TOKEN', ''));

        // No token configured = endpoint disabled. Fail CLOSED here (unlike the
        // ordering-status endpoint, which fails open): an open sink would let
        // anyone write into our delivery telemetry.
        if ($expected === '') {
            Log::warning('famedo mail-events: BREVO_WEBHOOK_TOKEN unset — webhook rejected');

            return response()->json(['message' => 'not configured'], 503);
        }

        // ⚠️ Accept the token from the QUERY STRING as well as the Authorization
        // header. Brevo's dashboard webhook form offers only a URL and event
        // checkboxes — there is no header field — so a header-only check would be
        // unusable without creating every webhook through the API. Brevo's own
        // documented options are credentials-in-the-URL or an IP allowlist, so
        // this is the sanctioned shape; treat the URL itself as a secret.
        $presented = (string) ($request->bearerToken() ?: $request->query('token', ''));

        if (!hash_equals($expected, $presented)) {
            // ⚠️ Logged loudly: a wrong token means Brevo is DISCARDING our events
            // (4xx = no retry), which looks exactly like "no bad news".
            Log::warning('famedo mail-events: rejected webhook with bad/missing token');

            return response()->json(['message' => 'unauthorized'], 401);
        }

        try {
            $stored = $this->ingest($request->all());
        } catch (Throwable $e) {
            // Swallow deliberately — see the no-retry rule above.
            Log::error('famedo mail-events: ingest failed: '.$e->getMessage());

            return response()->json(['ok' => true, 'stored' => 0], 200);
        }

        return response()->json(['ok' => true, 'stored' => $stored], 200);
    }

    /**
     * Brevo posts one event per request, but accept a batch too — costs nothing
     * and means a future change of theirs cannot silently drop events.
     */
    protected function ingest(array $payload): int
    {
        $events = array_is_list($payload) ? $payload : [$payload];
        $stored = 0;

        foreach ($events as $event) {
            if (is_array($event) && $this->ingestOne($event)) {
                $stored++;
            }
        }

        return $stored;
    }

    protected function ingestOne(array $payload): bool
    {
        $messageId = $this->normaliseMessageId((string) ($payload['message-id'] ?? $payload['message_id'] ?? ''));
        $event = (string) ($payload['event'] ?? '');

        if ($messageId === '' || $event === '') {
            return false;
        }

        // Ownership: not in our sent-log ⇒ a sibling tenant's mail. 200 + drop.
        $message = DB::table('mail_messages')->where('message_id', $messageId)->first();
        if (!$message) {
            return false;
        }

        $occurredAt = $this->occurredAt($payload);
        $isFailure = in_array($event, self::FAILURE_EVENTS, true);

        DB::table('mail_events')->insertOrIgnore([
            'message_id' => $messageId,
            'event' => $event,
            'reason' => isset($payload['reason']) ? mb_substr((string) $payload['reason'], 0, 2000) : null,
            // Raw kept for failures only, and only once the address is gone.
            'raw' => $isFailure ? json_encode($this->stripPii($payload)) : null,
            'occurred_at' => $occurredAt,
            'created_at' => now(),
        ]);

        // Denormalised verdict for the alarm query. Only terminal events may
        // overwrite an existing terminal state — a late `deferred` must never
        // downgrade a `delivered`.
        if (in_array($event, self::TERMINAL_EVENTS, true)) {
            DB::table('mail_messages')
                ->where('message_id', $messageId)
                ->where(function($q): void {
                    $q->whereNull('status')->orWhereNotIn('status', self::TERMINAL_EVENTS);
                })
                ->update(['status' => $event, 'status_at' => $occurredAt]);
        }

        if ($isFailure) {
            // One line per failure, no PII — the same shape as the geocoder
            // rescue log, feeding the same future analytics consumer.
            Log::notice(sprintf(
                'famedo mail-failure: event=%s type=%s domain=%s reason=%s',
                $event,
                (string) ($message->mail_type ?? 'unknown'),
                (string) ($message->recipient_domain ?? 'unknown'),
                mb_substr((string) ($payload['reason'] ?? ''), 0, 200),
            ));
        }

        return true;
    }

    /** Brevo quotes the id inconsistently; store the bare form both sides agree on. */
    protected function normaliseMessageId(string $id): string
    {
        return trim($id, " \t\n\r\0\x0B<>");
    }

    protected function occurredAt(array $payload): string
    {
        // `ts_event` (epoch) is the most reliable; `date` is a formatted string in
        // the account's timezone. Fall back to now() rather than dropping a row.
        foreach (['ts_event', 'ts'] as $key) {
            if (!empty($payload[$key]) && is_numeric($payload[$key])) {
                return date('Y-m-d H:i:s', (int) $payload[$key]);
            }
        }

        if (!empty($payload['date'])) {
            try {
                return (new \DateTimeImmutable((string) $payload['date']))->format('Y-m-d H:i:s');
            } catch (Throwable) {
                // fall through
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

    /** ⚠️ Never persist the recipient address — see the migration's DSGVO note. */
    protected function stripPii(array $payload): array
    {
        unset($payload['email'], $payload['sender_email'], $payload['to']);

        return $payload;
    }
}
