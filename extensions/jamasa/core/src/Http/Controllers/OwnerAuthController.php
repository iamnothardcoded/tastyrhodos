<?php

declare(strict_types=1);

namespace Jamasa\Core\Http\Controllers;

use Igniter\Api\Models\Token;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Login front door for the owner console.
 *
 * Validates a staff user's credentials against the admin guard (the same
 * path as TI's stock CreateToken) and mints a SHORT-LIVED token scoped to the
 * `owner` ability. The console SPA holds this token in memory only — it is
 * never baked into a static file (the flaw of the KDS/staff apps, acceptable
 * on a LAN tablet, not for an internet-facing owner panel).
 *
 * The token's blast radius is bounded by ConfineOwnerToken (owner-scoped
 * tokens reach only api/jamasa/*) and EnsureOwner (per-route `owner` check).
 *
 * This route is registered UNAUTHENTICATED (you have no token yet) but behind
 * a strict throttle — see Extension::boot().
 */
class OwnerAuthController extends Controller
{
    /** How long an owner session token stays valid before re-login. */
    protected int $tokenHours = 12;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $auth = app('admin.auth');
        $user = $auth->getByCredentials($credentials);

        if (!$user || !$auth->validateCredentials($user, $credentials) || !$user->is_activated) {
            return response()->json(['message' => 'Ungültige Zugangsdaten.'], 422);
        }

        // One live session per owner: drop prior console tokens so they don't
        // accumulate and a re-login invalidates an old (possibly leaked) one.
        $user->tokens()->where('name', 'owner-panel')->delete();

        $token = Token::createToken($user, 'owner-panel', ['owner']);
        $token->accessToken->forceFill([
            'expires_at' => now()->addHours($this->tokenHours),
        ])->save();

        return response()->json([
            'token' => $token->plainTextToken,
            'name' => $user->name ?? $user->first_name ?? $user->username,
            'expires_in' => $this->tokenHours * 3600,
        ]);
    }
}
