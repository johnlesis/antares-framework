<?php

declare(strict_types=1);

namespace Antares\Support;

final class CaseConverter
{
    public static function convert(string $name, string $case): string
    {
        return match($case) {
            'snake_case' => strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name)),
            'pascal_case' => ucfirst($name),
            'kebab_case' => strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name)),
            default => $name,
        };
    }
}