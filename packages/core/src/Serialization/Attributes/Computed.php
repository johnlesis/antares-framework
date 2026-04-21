<?php

declare(strict_types=1);

namespace Antares\Serialization\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Computed {}