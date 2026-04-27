<?php declare(strict_types=1);

namespace Antares\Http\ResolverInterfaces;

use Antares\Container\Container;
use Psr\Http\Message\ServerRequestInterface;

interface Resolvable
{
    public function resolve(ServerRequestInterface $request, Container $container): mixed;
}