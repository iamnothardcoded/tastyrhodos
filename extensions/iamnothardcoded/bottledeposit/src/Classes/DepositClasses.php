<?php

declare(strict_types=1);

namespace Iamnothardcoded\BottleDeposit\Classes;

use Iamnothardcoded\BottleDeposit\Models\DepositSettings;

/**
 * The configured deposit classes (code => label + amount). Ships with the
 * three German statutory/customary tiers; operators can edit amounts or add
 * classes in the settings. The class CODES are a stable vocabulary shared
 * with import tooling (menus.deposit_class stores the code) — rename labels,
 * not codes.
 */
class DepositClasses
{
    /**
     * German defaults, active until the settings are saved once. Codes are
     * the canonical contract (see famedo .knowledge/PFAND-BRIEF.md §3a).
     */
    public const DEFAULTS = [
        ['code' => 'einweg', 'label' => 'Einwegpfand', 'amount' => 0.25],
        ['code' => 'mehrweg', 'label' => 'Mehrwegpfand', 'amount' => 0.15],
        ['code' => 'mehrweg_bier', 'label' => 'Mehrwegpfand (Bier)', 'amount' => 0.08],
    ];

    /** @return array<int, array{code: string, label: string, amount: float}> */
    public static function all(): array
    {
        $rows = DepositSettings::get('classes');
        if (!is_array($rows) || $rows === []) {
            return self::DEFAULTS;
        }

        $classes = [];
        foreach ($rows as $row) {
            $code = trim((string)($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $classes[] = [
                'code' => $code,
                'label' => (string)($row['label'] ?? $code),
                'amount' => (float)($row['amount'] ?? 0),
            ];
        }

        return $classes !== [] ? $classes : self::DEFAULTS;
    }

    /** @return array<string, string> code => label (for select fields) */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $class) {
            $options[$class['code']] = sprintf(
                '%s (%s)',
                $class['label'],
                currency_format($class['amount']),
            );
        }

        return $options;
    }

    /** @return string[] valid class codes */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    public static function amountFor(?string $code): float
    {
        if ($code === null || $code === '') {
            return 0.0;
        }

        foreach (self::all() as $class) {
            if ($class['code'] === $code) {
                return $class['amount'];
            }
        }

        return 0.0;
    }

    public static function labelFor(?string $code): ?string
    {
        foreach (self::all() as $class) {
            if ($class['code'] === $code) {
                return $class['label'];
            }
        }

        return null;
    }
}
