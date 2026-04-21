<?php

declare(strict_types=1);

namespace Antares\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Patch
{
    public function __construct(
        public readonly string $path,
        public readonly int $statusCode = 200,
    ) {}
}
