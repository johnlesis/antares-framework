<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

final class MakeResponse extends GeneratorCommand
{
    protected function getPath(string $name): string
    {
        return getcwd() . "/app/Responses/{$name}.php";
    }

    protected function getStub(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Responses;

use Antares\Serialization\Attributes\ResponseDto;
use Antares\Serialization\Attributes\Hide;
use Antares\Serialization\Attributes\SerializeAs;

#[ResponseDto(case: 'snake_case')]
final readonly class {$name}
{
    public function __construct(
    ) {}
}
PHP;
    }
}