<?php

declare(strict_types=1);

namespace Antares\Middleware;

use Antares\Container\Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Pipeline
{
    private int $index = 0;

    /**
     * @param list<class-string<MiddlewareInterface>> $middleware
     * @param null|\Closure(\Throwable): ResponseInterface $onError Turns an exception into
     *        a response. When set, every layer is wrapped, so an exception thrown by the
     *        destination or by any middleware becomes a response that the middleware
     *        outside it still sees on the way out. When null, exceptions propagate.
     */
    public function __construct(
        private Container $container,
        private array $middleware,
        private \Closure $destination,
        private ?\Closure $onError = null,
    ) {}

    public function run(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->onError === null) {
            return $this->next($request);
        }

        try {
            return $this->next($request);
        } catch (\Throwable $e) {
            return ($this->onError)($e);
        }
    }

    private function next(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middleware[$this->index])) {
            return ($this->destination)($request);
        }

        // Resolved through the container so middleware can take constructor
        // dependencies, and can be registered as singletons or scoped bindings.
        $middleware = $this->container->make($this->middleware[$this->index]);
        $this->index++;

        return $middleware->handle(
            $request,
            fn($request) => $this->run($request),
        );
    }
}
