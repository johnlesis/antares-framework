<?php declare (strict_types=1);

namespace Antares;

use Antares\Exceptions\HttpExceptionInterface;
use Antares\Hydration\Exceptions\HydrationException;
use Antares\Router\Exceptions\MethodNotAllowedException;
use Antares\Router\Exceptions\RouteNotFoundException;
use Antares\Validation\Exceptions\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * Turns any exception into a JSON error response.
 *
 * Safe exceptions (validation, hydration, routing, HttpExceptionInterface, and
 * anything in $exceptionMap) show their status and message in every environment.
 * Everything else becomes a generic 500; with $debug on, the real error is added
 * under "debug". Every 5xx is logged.
 */
class ErrorHandler
{
    public const GENERIC_TITLE = 'Internal Server Error';

    /**
     * @param array<class-string<\Throwable>, int|array{0: int, 1: string}> $exceptionMap
     *        Extra exceptions to show: class => status, or class => [status, message].
     */
    public function __construct(
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
        private readonly array $exceptionMap = [],
        private readonly ?string $basePath = null,
    ) {}

    public function handle(\Throwable $e): Response
    {
        [$status, $title, $safe] = $this->describe($e);

        $body = [
            'type'   => 'https://antares.dev/errors',
            'title'  => $title,
            'status' => $status,
        ];

        if ($e instanceof ValidationException) {
            $body['errors'] = $e->getErrors();
        }

        if ($status >= 500) {
            $this->log($e, $status);
        }

        if ($this->debug && !$safe) {
            $body['debug'] = $this->debugInfo($e);
        }

        $headers = ['Content-Type' => 'application/json'];
        if ($e instanceof MethodNotAllowedException) {
            $headers['Allow'] = implode(', ', $e->allowedMethods);
        }

        return new Response(
            status: $status,
            headers: $headers,
            body: json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    /**
     * @return array{0: int, 1: string, 2: bool} status, title, whether the real message is shown
     */
    private function describe(\Throwable $e): array
    {
        $known = match (true) {
            $e instanceof ValidationException       => 422,
            $e instanceof HydrationException        => 400,
            $e instanceof RouteNotFoundException    => 404,
            $e instanceof MethodNotAllowedException => 405,
            $e instanceof HttpExceptionInterface    => $e->getStatusCode(),
            default                                 => null,
        };

        if ($known !== null) {
            return $this->isValidStatus($known)
                ? [$known, $e->getMessage(), true]
                : [500, self::GENERIC_TITLE, false];
        }

        foreach ($this->exceptionMap as $class => $mapping) {
            if ($e instanceof $class) {
                [$status, $title] = is_array($mapping) ? $mapping : [$mapping, $e->getMessage()];
                return [$status, $title, true];
            }
        }

        return [500, self::GENERIC_TITLE, false];
    }

    private function isValidStatus(int $status): bool
    {
        return $status >= 400 && $status <= 599;
    }

    private function log(\Throwable $e, int $status): void
    {
        $message = sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine());

        if ($this->logger !== null) {
            $this->logger->error($message, ['exception' => $e, 'status' => $status]);
            return;
        }

        error_log('[antares] ' . $message . "\n" . $e->getTraceAsString());
    }

    private function debugInfo(\Throwable $e): array
    {
        $info = [
            'message'   => $e->getMessage(),
            'exception' => $e::class,
            'file'      => $this->relativePath($e->getFile()) . ':' . $e->getLine(),
            'trace'     => array_map(
                fn (array $frame) => isset($frame['file'])
                    ? $this->relativePath($frame['file']) . ':' . ($frame['line'] ?? 0)
                    : ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'],
                $e->getTrace(),
            ),
        ];

        if ($e->getPrevious() !== null) {
            $info['previous'] = $this->debugInfo($e->getPrevious());
        }

        return $info;
    }

    private function relativePath(string $path): string
    {
        if ($this->basePath !== null && str_starts_with($path, rtrim($this->basePath, '/') . '/')) {
            return substr($path, strlen(rtrim($this->basePath, '/')) + 1);
        }

        return $path;
    }
}
