<?php

declare(strict_types=1);

namespace Iamnothardcoded\SignupDiscounts\Models;

use Igniter\Flame\Database\Model;
use Igniter\System\Actions\SettingsModel;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string|array $key, mixed $value)
 * @mixin SettingsModel
 */
class SignupDiscountSettings extends Model
{
    public array $implement = [SettingsModel::class];

    public string $settingsCode = 'iamnothardcoded_signupdiscounts_settings';

    public string $settingsFieldsConfig = 'signupdiscountsettings';
}
