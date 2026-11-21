<?php declare(strict_types=1);

namespace Antares\Serialization;

enum CaseStyle: string
{
    case SnakeCase  = 'snake_case';
    case CamelCase  = 'camel_case';
    case PascalCase = 'pascal_case';
    case KebabCase  = 'kebab_case';
}