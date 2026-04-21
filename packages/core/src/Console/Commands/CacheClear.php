<?php

declare(strict_types=1);

namespace Antares\Console\Commands;

final class CacheClear
{
    public function handle(?string $name): void
    {
        $path = getcwd() . '/storage/cache/routes.php';

        if (file_exists($path)) {
            unlink($path);
            echo "Cache cleared.\n";
        } else {
            echo "No cache to clear.\n";
        }

        $fingerprintPath = getcwd() . '/storage/cache/fingerprint';
        if (file_exists($fingerprintPath)) {
            unlink($fingerprintPath);
        }
    }
}