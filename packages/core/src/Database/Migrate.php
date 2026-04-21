<?php declare(strict_types=1);

namespace Antares\Database;

final class Migrate extends MigrationCommand
{
    public function handle():void
    {
        $pdo = $this->connect();
        $this->ensureMigrationsTable($pdo);

        $alreadyRan = $this->getRanMigrations($pdo);
        $localMigrations = $this->getMigrationFiles();
        $pending = array_filter($localMigrations, fn($file) => !in_array(basename($file), $alreadyRan));
        
        if (empty($pending)) {
            echo "Nothing to migrate.\n";
            return;
        }
        $batch = $this->getNextBatch($pdo);

        foreach ($pending as $file) {
            require_once $file;
            $className = $this->getClassName($file);
            $migration = new $className();
            $migration->up($pdo);

            $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")
                ->execute([basename($file), $batch]);

            echo "Migrated: {$file}\n";
        }
    }
}