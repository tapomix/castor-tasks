<?php

declare(strict_types=1);

/**
 * Autoload all PHP helper files from src/ directory for tests
 * This file is loaded by Composer autoload-dev
 */

$srcDir = __DIR__ . '/../src';

// Files to exclude from autoloading
$excludedFiles = [
    'context.php', // already loaded via main autoload
    'castor.dist.php', // template file
];

// Recursively load all PHP files from src/
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $fileName = $file->getFilename();

        // Skip excluded files
        if (in_array($fileName, $excludedFiles, true)) {
            continue;
        }

        require_once $file->getPathname();
    }
}
