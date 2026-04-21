<?php

declare(strict_types=1);

namespace Antares\Serialization\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class SerializeAs
{
    public function __construct(
        public readonly string $name,
    ) {}
}