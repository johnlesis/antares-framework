<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

final class MakeMiddleware extends GeneratorCommand
{
    protected function getPath(string $name): string
    {
        return getcwd() . "/app/Middleware/{$name}.php";
    }

    protected function getStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Middleware;

use Antares\Middleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class {$name} implements MiddlewareInterface
{
    public function handle(ServerRequestInterface \$request, callable \$next): ResponseInterface
    {
        return \$next(\$request);
    }
}
PHP;
    }
}