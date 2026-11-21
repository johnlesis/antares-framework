<?php

declare(strict_types=1);

namespace Antares\Serialization\Attributes;

use Antares\Serialization\CaseStyle;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ResponseDto
{
    public function __construct(
        public readonly CaseStyle $case = CaseStyle::CamelCase,
    ) {}
}