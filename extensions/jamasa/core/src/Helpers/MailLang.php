<?php

declare(strict_types=1);

namespace Jamasa\Core\Helpers;

use Igniter\System\Models\Language;

/**
 * Locale-pinned translations for customer-facing mail templates.
 *
 * Mail renders in the locale of whatever context triggered it: with the sync
 * queue an admin status change renders under the ADMIN's locale (English),
 * and a future queue worker would render under config app.locale — both wrong
 * for customer mail. Customer mail must always follow the site default
 * language, so every string resolves against Language::getDefault() explicitly.
 */
final class MailLang
{
    public static function get(string $key, array $replace = []): string
    {
        return trans('jamasa.core::default.mail.'.$key, $replace, Language::getDefault()?->code);
    }
}
