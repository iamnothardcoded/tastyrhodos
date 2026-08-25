<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Channel attribution (`?src=`) — the CONSUMER side of the write-ahead
 * tagging convention locked 2026-08-07: every published ordering link
 * carries ?src=<surface> (maps, flyer-2608, tuete, website-maps, …).
 *
 * The param exists only on the FIRST request — Livewire and normal
 * navigation drop it — so it is captured here and stashed in the SESSION,
 * never read from the URL again. Deliberately NO extra cookie: the session
 * cookie already exists, so the "only technically necessary cookies"
 * banner stays honest (first-party, server-side, no consent tool needed).
 * Tradeoff accepted: attribution lives as long as the session — a visitor
 * who lands today and orders next week counts as untagged.
 *
 * Semantics: LAST touch wins — a new tagged arrival overwrites the stash
 * (a flyer scan after a maps visit is the fresher marketing touch, and the
 * param only ever appears once per landing, so this cannot flap).
 *
 * Two consumers of what happens here:
 *  - orders.src, stamped by the afterSaveOrder listener (only orders
 *    answer "which surface actually pays");
 *  - the NOTICE log line below = the VISIT signal (a flyer got scanned
 *    even if nobody ordered) — same shape as the geocoder rescue log,
 *    feeding the same future analytics consumer.
 *
 * Vocabulary is lowercase ASCII tokens (CLAUDE.md `?src=` TODO); the
 * value arrives from the address bar and is attacker-controlled, so it is
 * validated strictly — a mangled or hostile param never reaches the
 * session, the log, or the DB.
 */
class CaptureChannelSource
{
    public const SESSION_KEY = 'famedo_src';

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && $request->hasSession()) {
            $raw = $request->query('src');
            if (is_string($raw)) {
                $src = strtolower(trim($raw));
                if (preg_match('/^[a-z0-9][a-z0-9-]{0,31}$/', $src)
                    && $request->session()->get(self::SESSION_KEY) !== $src) {
                    $request->session()->put(self::SESSION_KEY, $src);
                    Log::notice('famedo src captured: '.$src);
                }
            }
        }

        return $next($request);
    }
}
