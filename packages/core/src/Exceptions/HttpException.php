<?php declare(strict_types=1);

namespace Antares\Exceptions;

use RuntimeException;

/**
 * An error meant for the client: its status code and message are always shown.
 */
class HttpException extends RuntimeException implements HttpExceptionInterface
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