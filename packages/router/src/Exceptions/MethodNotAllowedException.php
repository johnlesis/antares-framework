<?php

declare(strict_types=1);

namespace Antares\Router\Exceptions;

use RuntimeException;

/**
 * The path exists, but not for this HTTP method. Code 405.
 */
final class MethodNotAllowedException extends RuntimeException
{
    /**
     * @param string[] $allowedMethods Methods the path does accept, for the `Allow` header.
     */
    public function __construct(
        public readonly string $httpMethod,
        public readonly string $uri,
        public readonly array $allowedMethods,
    ) {
        parent::__construct('Method not allowed', 405);
    }
}
