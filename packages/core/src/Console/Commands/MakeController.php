<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

final class MakeController extends GeneratorCommand
{
    protected function getPath(string $name): string
    {
        return getcwd() . "/app/Controllers/{$name}.php";
    }

    protected function getStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Controllers;

use Antares\Router\Attributes\Get;
use Antares\Router\Attributes\Post;
use Antares\Router\Attributes\Put;
use Antares\Router\Attributes\Patch;
use Antares\Router\Attributes\Delete;

final class {$name}
{
    public function __construct(
    ) {}
}
PHP;
    }
}