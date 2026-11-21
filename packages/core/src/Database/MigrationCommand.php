<?php declare(strict_types=1);

namespace Antares\Database;
use PDO;

abstract class MigrationCommand
{
    /** Ports used when DB_PORT is not set. */
    private const DEFAULT_PORTS = [
        'mysql'  => '3306',
        'pgsql'  => '5432',
        'sqlsrv' => '1433',
        'oci'    => '1521',
    ];

    public function connect(): PDO
    {
        return new PDO(
            $this->dsn(),
            $_ENV['DB_USERNAME'] ?? '',
            $_ENV['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * The PDO connection string for DB_DRIVER. Set DB_DSN to use your own instead,
     * e.g. an Oracle TNS name or extra driver options.
     */
    public function dsn(): string
    {
        if (!empty($_ENV['DB_DSN'])) {
            return $_ENV['DB_DSN'];
        }

        $driver   = $_ENV['DB_DRIVER']   ?? 'mysql';
        $host     = $_ENV['DB_HOST']     ?? '127.0.0.1';
        $port     = $_ENV['DB_PORT']     ?? self::DEFAULT_PORTS[$driver] ?? '';
        $database = $_ENV['DB_DATABASE'] ?? '';

        return match ($driver) {
            'mysql'  => "mysql:host={$host};port={$port};dbname={$database};charset=" . ($_ENV['DB_CHARSET'] ?? 'utf8mb4'),
            'pgsql'  => "pgsql:host={$host};port={$port};dbname={$database}",
            // DB_DATABASE is the database file path, or :memory:
            'sqlite' => "sqlite:{$database}",
            'sqlsrv' => "sqlsrv:Server={$host},{$port};Database={$database}",
            // DB_DATABASE is the Oracle service name (Easy Connect syntax)
            'oci'    => "oci:dbname=//{$host}:{$port}/{$database}",
            default  => throw new \RuntimeException(
                "Unsupported DB_DRIVER '{$driver}'. Use mysql, pgsql, sqlite, sqlsrv or oci, or set DB_DSN."
            ),
        };
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
            'sqlite' => $pdo->exec("
                    CREATE TABLE IF NOT EXISTS migrations (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        migration VARCHAR(255) NOT NULL,
                        batch INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
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