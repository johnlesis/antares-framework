<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

final class MakeMigration extends GeneratorCommand
{
    protected function getPath(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        return getcwd() . "/database/migrations/{$timestamp}_{$name}.php";
    }

    protected function getStub(string $name): string
    {
        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
        return <<<PHP
<?php

declare(strict_types=1);

final class {$className}
{
    public function up(\PDO \$pdo): void
    {
    }

    public function down(\PDO \$pdo): void
    {
    }
}
PHP;
    }
}