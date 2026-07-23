<?php

declare(strict_types=1);

namespace Iamnothardcoded\TaxClasses\Models;

use Igniter\Flame\Database\Model;
use Igniter\System\Actions\SettingsModel;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool set(string|array $key, mixed $value)
 * @mixin SettingsModel
 */
class TaxClassSettings extends Model
{
    public array $implement = [SettingsModel::class];

    public string $settingsCode = 'iamnothardcoded_taxclasses_settings';

    public string $settingsFieldsConfig = 'taxclasssettings';
}
