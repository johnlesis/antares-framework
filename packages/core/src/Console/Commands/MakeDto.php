<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

final class MakeDto extends GeneratorCommand
{
    protected function getPath(string $name): string
    {
        return getcwd() . "/app/DTOs/{$name}.php";
    }

    protected function getStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\DTOs;

use Antares\Validation\Attributes\Dto;

#[Dto]
final readonly class {$name}
{
    public function __construct(
    ) {}
}
PHP;
    }
}