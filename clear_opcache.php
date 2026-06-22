<?php
// Clear OPcache by touching all PHP files in app directory
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/app')
);
foreach ($iterator as $file) {
    if ($file->getExtension() === 'php') {
        touch($file->getPathname());
    }
}
echo "Touched all PHP files in app/ to invalidate OPcache.\n";

// Also try opcache_reset if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset.\n";
}
