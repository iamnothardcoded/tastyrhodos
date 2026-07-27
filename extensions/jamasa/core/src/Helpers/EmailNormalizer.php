<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

/**
 * Canonicalizes email addresses for ACCOUNT IDENTITY (lookup, creation,
 * rate-limit keys) — not for delivery; always SEND to the address as typed.
 *
 * Why: gmail dot-variants and +tags are the standard trick for farming
 * one-time welcome discounts (max.muster / maxmuster / max+1@gmail.com are
 * the same inbox), and they also create accidental duplicate accounts.
 * Rules: lowercase; strip a "+tag" for every domain (a plus-address is
 * virtually always the same mailbox owner); strip dots in the local part
 * ONLY for gmail-class domains (dots are significant elsewhere, RFC-wise);
 * googlemail.com → gmail.com.
 */
class EmailNormalizer
{
    protected const GMAIL_DOMAINS = ['gmail.com', 'googlemail.com'];

    public static function normalize(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if (!str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        $local = explode('+', $local, 2)[0];

        if (in_array($domain, self::GMAIL_DOMAINS, true)) {
            $local = str_replace('.', '', $local);
            $domain = 'gmail.com';
        }

        return $local.'@'.$domain;
    }
}
