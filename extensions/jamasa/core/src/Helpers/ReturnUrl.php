<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

/**
 * Captures a post-login/register return URL into the session intended-URL — but
 * ONLY if it points back at our own site. A naive `str_starts_with($url, url('/'))`
 * is bypassable (`https://our.host.evil.com/…`, `https://our.host@evil.com/…`,
 * `//evil.com`), which is an open-redirect → post-auth phishing hop. Here we
 * compare the parsed host to the request host and reject protocol-relative URLs.
 */
class ReturnUrl
{
    public static function isLocal(?string $url): bool
    {
        if (!filled($url) || str_starts_with($url, '//')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        // Relative path (no host) is fine; an absolute URL must match our host.
        return $host === null || $host === request()->getHost();
    }

    /** Validate + stash as the intended URL; returns the accepted URL or null. */
    public static function capture(?string $url): ?string
    {
        if (!self::isLocal($url)
            || str_contains((string)$url, '/login')
            || str_contains((string)$url, '/register')) {
            return null;
        }

        redirect()->setIntendedUrl($url);

        return $url;
    }

    /** Add ?welcome=1 in the QUERY, before any #fragment (a naive append puts
     *  it inside the fragment, where request()->query() never sees it). */
    public static function withWelcomeFlag(string $url): string
    {
        [$base, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        if (!str_contains($base, 'welcome=1')) {
            $base .= (str_contains($base, '?') ? '&' : '?').'welcome=1';
        }

        return $fragment === null ? $base : $base.'#'.$fragment;
    }
}
