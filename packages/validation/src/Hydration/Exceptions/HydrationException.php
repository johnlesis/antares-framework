<?php declare(strict_types=1); 

namespace Antares\Hydration\Exceptions;

use RuntimeException;

final class HydrationException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}