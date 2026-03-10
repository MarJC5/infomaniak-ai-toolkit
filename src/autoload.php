<?php

/**
 * PSR-4 autoloader for the Infomaniak AI Toolkit package.
 *
 * @since 1.0.0
 *
 * @package WordPress\InfomaniakAiToolkit
 */

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'WordPress\\InfomaniakAiToolkit\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);

    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);

    if (strpos($relativeClass, '..') !== false) {
        return;
    }

    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
