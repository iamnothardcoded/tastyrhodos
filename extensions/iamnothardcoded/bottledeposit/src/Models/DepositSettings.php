<?php

declare(strict_types=1);

namespace Iamnothardcoded\BottleDeposit\Models;

use Igniter\Flame\Database\Model;
use Igniter\System\Actions\SettingsModel;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string|array $key, mixed $value)
 * @mixin SettingsModel
 */
class DepositSettings extends Model
{
    public array $implement = [SettingsModel::class];

    public string $settingsCode = 'iamnothardcoded_bottledeposit_settings';

    public string $settingsFieldsConfig = 'depositsettings';
}
