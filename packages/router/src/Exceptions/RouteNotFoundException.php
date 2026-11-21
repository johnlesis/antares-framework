<?php

declare(strict_types=1);

namespace Antares\Router\Exceptions;

use RuntimeException;

/**
 * No route matches the request path. Code 404.
 */
final class RouteNotFoundException extends RuntimeException
{
    public function __construct(
        public readonly string $httpMethod,
        public readonly string $uri,
    ) {
        parent::__construct('Route not found', 404);
    }
}
