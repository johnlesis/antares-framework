<?php

declare(strict_types=1);

namespace Antares\Support;

use Antares\Serialization\CaseStyle;

final class CaseConverter
{
    public static function convert(string $name, CaseStyle $case): string
    {
        return match($case) {
            CaseStyle::SnakeCase  => strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name)),
            CaseStyle::PascalCase => ucfirst($name),
            CaseStyle::KebabCase  => strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name)),
            CaseStyle::CamelCase  => $name,
        };
    }
}