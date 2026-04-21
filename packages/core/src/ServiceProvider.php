<?php declare (strict_types=1);

namespace Antares;

use Antares\Container\Container;

interface ServiceProvider
{
    public function register(Container $container): void;
}