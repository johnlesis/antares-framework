<?php

declare(strict_types=1);

namespace Antares\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Pipeline
{
    private int $index = 0;

    public function __construct(
        private array $middleware,
        private \Closure $destination,
    ) {}

    public function run(ServerRequestInterface $request): ResponseInterface
    {
        if (!isset($this->middleware[$this->index])) {
            return ($this->destination)($request);
        }

        $middleware = new $this->middleware[$this->index]();
        $this->index++;

        return $middleware->handle(
            $request,
            fn($request) => $this->run($request),
        );
    }
}