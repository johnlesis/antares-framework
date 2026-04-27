<?php declare(strict_types=1);

namespace Antares\Http\Attributes;

use Antares\Container\Container;
use Antares\Http\ResolverInterfaces\Resolvable;
use Attribute;
use Psr\Http\Message\ServerRequestInterface;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Inject implements Resolvable
{
    public function __construct(public readonly string $resolverClass) {}

    public function resolve(ServerRequestInterface $request, Container $container): mixed
    {
        return $container->make($this->resolverClass)->resolve($request);
    }
}