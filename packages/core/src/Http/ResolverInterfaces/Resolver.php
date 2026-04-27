<?php declare(strict_types=1);

namespace Antares\Http\ResolverInterfaces;

use Psr\Http\Message\ServerRequestInterface;

interface Resolver
{
    public function resolve(ServerRequestInterface $request): mixed;
}