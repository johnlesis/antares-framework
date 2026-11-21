<?php

declare(strict_types=1);

namespace {
    // Stand-in for FrankenPHP's worker function: replays a queue of requests,
    // returning false once the queue is empty (FrankenPHP's "stop the worker").
    if (!function_exists('frankenphp_handle_request')) {
        function frankenphp_handle_request(callable $handler): bool
        {
            $request = array_shift(\Antares\Tests\Core\Runtime\FakeFranken::$queue);
            if ($request === null) {
                return false;
            }

            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = $request;
            $handler();
            \Antares\Tests\Core\Runtime\FakeFranken::$handled++;

            return \Antares\Tests\Core\Runtime\FakeFranken::$queue !== [];
        }
    }
}

namespace Antares\Tests\Core\Runtime {

    use Antares\Application;
    use Antares\Console\Commands\MakeGuard;
    use Antares\Container\Container;
    use Antares\Http\ResolverInterfaces\Guard;
    use Antares\Router\Attributes\Get;
    use Antares\Router\Router;
    use Antares\ServiceProvider;
    use PHPUnit\Framework\TestCase;

    final class FakeFranken
    {
        /** @var list<string> */
        public static array $queue = [];
        public static int $handled = 0;
    }

    final class BootCounter implements ServiceProvider
    {
        public static int $boots = 0;

        public function register(Container $container): void
        {
            self::$boots++;
        }
    }

    final class PingController
    {
        #[Get('/ping')]
        public function ping(): array
        {
            return ['pong' => true];
        }
    }

    final class PingRoutes implements ServiceProvider
    {
        public function register(Container $container): void
        {
            $container->make(Router::class)->register(PingController::class);
        }
    }

    class RuntimeAndGeneratorTest extends TestCase
    {
        private array $envBackup;
        private array $serverBackup;

        protected function setUp(): void
        {
            $this->envBackup = $_ENV;
            $this->serverBackup = $_SERVER;
            $_ENV['APP_DOCS'] = 'false';
            unset($_ENV['MAX_REQUESTS'], $_SERVER['MAX_REQUESTS']);
            FakeFranken::$queue = [];
            FakeFranken::$handled = 0;
            BootCounter::$boots = 0;
        }

        protected function tearDown(): void
        {
            $_ENV = $this->envBackup;
            $_SERVER = $this->serverBackup;
        }

        private function runWorker(): string
        {
            ob_start();
            try {
                Application::create(sys_get_temp_dir())
                    ->providers([BootCounter::class])
                    ->routeProviders([PingRoutes::class])
                    ->runWorker();
            } finally {
                $output = (string) ob_get_clean();
            }
            return $output;
        }

        public function test_worker_boots_once_and_handles_every_request(): void
        {
            FakeFranken::$queue = ['/ping', '/ping', '/ping'];

            $output = $this->runWorker();

            $this->assertSame(3, FakeFranken::$handled);
            $this->assertSame(1, BootCounter::$boots);
            $this->assertSame(str_repeat('{"pong":true}', 3), $output);
        }

        public function test_worker_stops_after_max_requests(): void
        {
            FakeFranken::$queue = ['/ping', '/ping', '/ping', '/ping'];
            $_SERVER['MAX_REQUESTS'] = '2';

            $this->runWorker();

            $this->assertSame(2, FakeFranken::$handled);
            $this->assertCount(2, FakeFranken::$queue);
        }

        public function test_make_guard_generates_a_working_guard(): void
        {
            $dir = sys_get_temp_dir() . '/antares-make-guard-' . bin2hex(random_bytes(4));
            mkdir($dir);
            $cwd = getcwd();

            try {
                chdir($dir);
                ob_start();
                (new MakeGuard())->handle('GeneratedTestGuard');
                ob_end_clean();

                $file = $dir . '/app/Guards/GeneratedTestGuard.php';
                $this->assertFileExists($file);

                require $file;
                $this->assertTrue(is_subclass_of('App\\Guards\\GeneratedTestGuard', Guard::class));
            } finally {
                chdir($cwd);
                @unlink($dir . '/app/Guards/GeneratedTestGuard.php');
                @rmdir($dir . '/app/Guards');
                @rmdir($dir . '/app');
                @rmdir($dir);
            }
        }
    }
}
