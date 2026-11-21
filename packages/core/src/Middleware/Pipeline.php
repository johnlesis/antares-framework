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
     */
    public function __construct(
        private Container $container,
        private array $middleware,
        private \Closure $destination,
    ) {}

    public function run(ServerRequestInterface $request): ResponseInterface
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
