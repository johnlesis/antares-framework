<?php declare(strict_types=1);

namespace Antares\Http\Guards;

use Psr\Http\Message\ServerRequestInterface;

interface Guard
{
    public function resolve(ServerRequestInterface $request): mixed;
}