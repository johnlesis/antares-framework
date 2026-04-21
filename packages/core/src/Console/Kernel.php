<?php

declare(strict_types=1);

namespace Antares\Console;

use Antares\Console\Commands\CacheClear;
use Antares\Console\Commands\MakeController;
use Antares\Console\Commands\MakeDto;
use Antares\Console\Commands\MakeGuard;
use Antares\Console\Commands\MakeMiddleware;
use Antares\Console\Commands\MakeResponse;
use Antares\Database\Migrate;
use Antares\Database\MigrateRollback;
use Antares\Console\Commands\MakeMigration;

final class Kernel
{

    private array $commands = [
        'make:controller'  => MakeController::class,
        'make:dto'         => MakeDto::class,
        'make:response'    => MakeResponse::class,
        'make:middleware'  => MakeMiddleware::class,
        'make:guard'       => MakeGuard::class,
        'make:migration'   => MakeMigration::class,
        'migrate'          => Migrate::class,
        'migrate:rollback' => MigrateRollback::class,
        'cache:clear'      => CacheClear::class,
    ];

    public function handle(array $argv): void
    {
        if (!isset($argv[1])) {
            echo "Usage: antares <command> <name>\n";
            echo "\nAvailable commands:\n";
            foreach ($this->commands as $name => $class) {
                echo "  {$name}\n";
            }
            return;
        }

        $command = $argv[1];
        $name    = $argv[2] ?? null;
        $path    = null;

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--path=')) {
                $path = substr($arg, 7);
                break;
            }
        }

        if (!isset($this->commands[$command])) {
            echo "Unknown command: {$command}\n";
            echo "Run 'antares' to see available commands.\n";
            return;
        }

        $commandClass = $this->commands[$command];
        $instance = new $commandClass();

        if (method_exists($instance, 'handle')) {
            $argCount = (new \ReflectionMethod($instance, 'handle'))->getNumberOfParameters();
            if ($argCount === 0) {
                $instance->handle();
            } else {
                $instance->handle($name, $path);
            }
        }
    }
}