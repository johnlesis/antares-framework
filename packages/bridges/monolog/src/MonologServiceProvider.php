<?php

declare(strict_types=1);

namespace Antares\Monolog;

use Antares\Container\Container;
use Antares\ServiceProvider;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class MonologServiceProvider implements ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(LoggerInterface::class, function () {
            $channel = $_ENV['LOG_CHANNEL'] ?? 'file';
            $level   = $_ENV['LOG_LEVEL'] ?? 'debug';
            $name    = $_ENV['APP_NAME'] ?? 'antares';

            $logger = new Logger($name);

            match($channel) {
                'stderr' => $logger->pushHandler(new StreamHandler('php://stderr', $level)),
                'stdout' => $logger->pushHandler(new StreamHandler('php://stdout', $level)),
                default  => $logger->pushHandler(new RotatingFileHandler(
                    ($_ENV['APP_BASE_PATH'] ?? dirname(getcwd())) . '/storage/logs/app.log',
                    30,
                    $level
                )),
            };

            return $logger;
        });

        $container->singleton(Logger::class, fn() => $container->make(LoggerInterface::class));
    }
}