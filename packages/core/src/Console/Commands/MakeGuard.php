<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

final class MakeGuard extends GeneratorCommand
{
    protected function getPath(string $name): string
    {
        return getcwd() . "/app/Guards/{$name}.php";
    }

    protected function getStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Guards;

use Antares\Http\Guards\Guard;
use Antares\Exceptions\HttpException;
use Psr\Http\Message\ServerRequestInterface;

final class {$name} implements Guard
{
    public function resolve(ServerRequestInterface \$request): mixed
    {
        throw new HttpException(401, 'Unauthorized');
    }
}
PHP;
    }
}