<?php declare(strict_types=1);

namespace Antares\Database;
use PDO;

final class MigrateRollback extends MigrationCommand
{
    public function handle():void
    {
        $pdo = $this->connect();
        $this->ensureMigrationsTable($pdo);

        $lastBatch = $pdo->query("SELECT MAX(batch) FROM migrations")->fetchColumn();
        $stmt = $pdo->prepare("SELECT migration FROM migrations WHERE batch = ?");
        $stmt->execute([$lastBatch]);
        $lastMigrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($lastMigrations)) {
            echo "Nothing to rollback.\n";
            return;
        }

        $lastMigrations = array_reverse($lastMigrations);
        
        foreach ($lastMigrations as $file) {
            $path = getcwd() . '/database/migrations/' . $file;
            require_once $path;
            $className = $this->getClassName($file);
            $migration = new $className();
            $migration->down($pdo);
            $pdo->prepare("DELETE FROM migrations WHERE migration = ?")
                ->execute([$file]);

            echo "Rolled Back Migration: {$file}\n";
        }
    }
}