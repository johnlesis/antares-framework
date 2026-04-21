<?php declare(strict_types=1);

namespace Antares\Exceptions;

use RuntimeException;
use Throwable;

class SerializationException extends RuntimeException
{
    public function __construct(int $code = 0, string $message = "")
    {
        parent::__construct($message, $code);
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }
}