<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

/**
 * Canonicalizes email addresses for ACCOUNT IDENTITY (the `normalized_email`
 * column, lookups, rate-limit keys) — NOT for delivery; mail always goes to the
 * address as typed/stored.
 *
 * Why: gmail dot-variants and provider +tags are the standard trick for farming
 * one-time welcome discounts (max.muster / maxmuster / max+1@gmail.com are the
 * same inbox), and they create accidental duplicate accounts.
 *
 * Rules (deliberately conservative — over-merging two DISTINCT mailboxes into
 * one account is a cross-customer data leak, so we only fold what is safe):
 *  - lowercase + trim;
 *  - strip a "+tag" ONLY for known consumer providers where "+" is guaranteed a
 *    subaddress of the same inbox (gmail, outlook/hotmail/live, yahoo, icloud,
 *    proton, gmx, web.de) — NOT for arbitrary domains, where sales+x@firma.de
 *    may be a genuinely different mailbox;
 *  - strip dots in the local part ONLY for gmail-class domains (dots are
 *    significant elsewhere, RFC 5321);
 *  - googlemail.com → gmail.com.
 * If stripping would empty the local part, keep the original local (an
 * "+tag@domain" is not a real login and must not collapse onto "@domain").
 */
class EmailNormalizer
{
    protected const GMAIL_DOMAINS = ['gmail.com', 'googlemail.com'];

    /** Providers where a "+tag" is a same-inbox subaddress. */
    protected const PLUS_TAG_DOMAINS = [
        'gmail.com', 'googlemail.com',
        'outlook.com', 'hotmail.com', 'live.com', 'msn.com',
        'yahoo.com', 'yahoo.de',
        'icloud.com', 'me.com',
        'proton.me', 'protonmail.com',
        'gmx.de', 'gmx.net', 'web.de',
    ];

    public static function normalize(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $original = $local;

        if (in_array($domain, self::PLUS_TAG_DOMAINS, true)) {
            $local = explode('+', $local, 2)[0];
        }

        if (in_array($domain, self::GMAIL_DOMAINS, true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        // Never let stripping produce an empty local part.
        if ($local === '') {
            $local = $original;
        }

        return $local.'@'.$domain;
    }
}
