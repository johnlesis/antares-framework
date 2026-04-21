<?php declare(strict_types=1);

namespace Antares\Http\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Guards
{
    public function __construct(
        public readonly string $guardClass,
    ) {}
}