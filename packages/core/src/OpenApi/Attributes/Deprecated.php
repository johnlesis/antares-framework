<?php

declare(strict_types=1);

namespace Antares\OpenApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Deprecated {}