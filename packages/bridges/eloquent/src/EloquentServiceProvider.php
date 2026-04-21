<?php

declare(strict_types=1);

namespace Antares\Eloquent;

use Antares\Container\Container;
use Antares\ServiceProvider;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\DatabaseManager;

final class EloquentServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $capsule = new Capsule();

        $capsule->addConnection([
            'driver'    => $_ENV['DB_DRIVER']   ?? 'mysql',
            'host'      => $_ENV['DB_HOST']      ?? '127.0.0.1',
            'port'      => $_ENV['DB_PORT']      ?? '3306',
            'database'  => $_ENV['DB_DATABASE']  ?? '',
            'username'  => $_ENV['DB_USERNAME']  ?? '',
            'password'  => $_ENV['DB_PASSWORD']  ?? '',
            'charset'   => $_ENV['DB_CHARSET']   ?? 'utf8mb4',
            'collation' => $_ENV['DB_COLLATION']  ?? 'utf8mb4_unicode_ci',
            'prefix'    => $_ENV['DB_PREFIX']    ?? '',
        ]);

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $container->singleton(Capsule::class, fn() => $capsule);
        $container->singleton(DatabaseManager::class, fn() => $capsule->getDatabaseManager());
    }
}