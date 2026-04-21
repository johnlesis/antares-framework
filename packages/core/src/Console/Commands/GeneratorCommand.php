<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

abstract class GeneratorCommand
{
    abstract protected function getStub(string $name): string;
    abstract protected function getPath(string $name): string;

    public function handle(?string $name, ?string $path = null): void
    {
        if ($name === null) {
            echo "Please provide a name.\n";
            return;
        }

        $resolvedPath = $path
            ? getcwd() . "/{$path}/{$name}.php"
            : $this->getPath($name);

        $stub = $this->getStub($name);

        if (file_exists($resolvedPath)) {
            echo "File already exists: {$resolvedPath}\n";
            return;
        }

        $directory = dirname($resolvedPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($resolvedPath, $stub);
        echo "Created: {$resolvedPath}\n";
    }
}