<?php

declare(strict_types=1);

namespace Antares\Tests\Core\Migrations;

use Antares\Database\MigrationCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MigrationConnectionTest extends TestCase
{
    private array $envBackup;

    protected function setUp(): void
    {
        $this->envBackup = $_ENV;
        foreach (['DB_DSN', 'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_CHARSET'] as $key) {
            unset($_ENV[$key]);
        }
    }

    protected function tearDown(): void
    {
        $_ENV = $this->envBackup;
    }

    private function dsn(array $env): string
    {
        $_ENV = array_merge($_ENV, $env);
        return (new class extends MigrationCommand {})->dsn();
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function drivers(): array
    {
        $db = ['DB_HOST' => 'db.local', 'DB_DATABASE' => 'app'];

        return [
            'mysql, default port'    => [['DB_DRIVER' => 'mysql'] + $db, 'mysql:host=db.local;port=3306;dbname=app;charset=utf8mb4'],
            'mysql, custom charset'  => [['DB_DRIVER' => 'mysql', 'DB_CHARSET' => 'latin1'] + $db, 'mysql:host=db.local;port=3306;dbname=app;charset=latin1'],
            'pgsql, default port'    => [['DB_DRIVER' => 'pgsql'] + $db, 'pgsql:host=db.local;port=5432;dbname=app'],
            'sqlite, file path'      => [['DB_DRIVER' => 'sqlite', 'DB_DATABASE' => '/data/app.sqlite'], 'sqlite:/data/app.sqlite'],
            'sqlsrv, default port'   => [['DB_DRIVER' => 'sqlsrv'] + $db, 'sqlsrv:Server=db.local,1433;Database=app'],
            'oracle, default port'   => [['DB_DRIVER' => 'oci', 'DB_DATABASE' => 'ORCLPDB1'] + $db, 'oci:dbname=//db.local:1521/ORCLPDB1'],
            'oracle, custom port'    => [['DB_DRIVER' => 'oci', 'DB_PORT' => '1522', 'DB_DATABASE' => 'XEPDB1'] + $db, 'oci:dbname=//db.local:1522/XEPDB1'],
            'no DB_DRIVER: mysql'    => [$db, 'mysql:host=db.local;port=3306;dbname=app;charset=utf8mb4'],
        ];
    }

    #[DataProvider('drivers')]
    public function test_each_driver_gets_its_own_connection_string(array $env, string $expected): void
    {
        $this->assertSame($expected, $this->dsn($env));
    }

    public function test_db_dsn_overrides_everything(): void
    {
        $this->assertSame('oci:dbname=PROD_TNS', $this->dsn(['DB_DRIVER' => 'oci', 'DB_DSN' => 'oci:dbname=PROD_TNS']));
    }

    public function test_unknown_driver_fails_with_a_clear_message(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Unsupported DB_DRIVER 'mongo'");
        $this->dsn(['DB_DRIVER' => 'mongo']);
    }
}
