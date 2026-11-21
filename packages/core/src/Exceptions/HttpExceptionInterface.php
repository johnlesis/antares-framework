<?php

declare(strict_types=1);

namespace Antares\Exceptions;

/**
 * Marks an exception as safe to show to the client.
 *
 * The ErrorHandler responds with getStatusCode() and the exception's message in
 * every environment. Exceptions that don't implement this (and aren't mapped with
 * Application::exceptions()) are hidden behind a generic 500 unless APP_DEBUG is on.
 */
interface HttpExceptionInterface extends \Throwable
{
    public function getStatusCode(): int;
}
