<?php declare(strict_types=1);

namespace Antares\Database;
use PDO;

abstract class MigrationCommand
{
    public function connect(): PDO
    {
        $driver   = $_ENV['DB_DRIVER']   ?? 'mysql';
        $host     = $_ENV['DB_HOST']     ?? '127.0.0.1';
        $port     = $_ENV['DB_PORT']     ?? '3306';
        $database = $_ENV['DB_DATABASE'] ?? '';
        $username = $_ENV['DB_USERNAME'] ?? '';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        return new PDO(
            "{$driver}:host={$host};port={$port};dbname={$database}",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function ensureMigrationsTable(PDO $pdo): void
    {
        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';

        match($driver) {
            'sqlsrv' => $pdo->exec("
                IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='migrations' AND xtype='U')
                CREATE TABLE migrations (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    created_at DATETIME DEFAULT GETDATE()
                )
            "),
            'oci' => $pdo->exec("
                BEGIN
                    EXECUTE IMMEDIATE '
                        CREATE TABLE migrations (
                            id NUMBER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                            migration VARCHAR2(255) NOT NULL,
                            batch NUMBER NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ';
                EXCEPTION
                    WHEN OTHERS THEN
                        IF SQLCODE != -955 THEN
                            RAISE;
                        END IF;
                END;
            "),
            'pgsql'  => $pdo->exec("
                    CREATE TABLE IF NOT EXISTS migrations (
                        id SERIAL PRIMARY KEY,
                        migration VARCHAR(255) NOT NULL,
                        batch INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                "),
            default => $pdo->exec("
                CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTO_INCREMENT,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            "),
        };
    }

    public function getRanMigrations(PDO $pdo): array
    {
        return $pdo->query("SELECT migration FROM migrations")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getMigrationFiles(): array
    {
        $path = getcwd() . '/database/migrations';
        
        if (!is_dir($path)) {
            return [];
        }
        
        $files = glob($path . '/*.php');
        sort($files);
        
        return $files;
    }

    public function getClassName(string $fileName): string
    {
        $basename = basename($fileName, '.php');
        $name = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $basename, );
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    public function getNextBatch(PDO $pdo): int
    {
        $last = $pdo->query("SELECT MAX(batch) FROM migrations")->fetchColumn();
        return ($last ?? 0) + 1;
    }
}