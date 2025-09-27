<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/**
 * Rector configuration for automated PHP refactoring
 *
 * This configuration enables modern PHP patterns and removes deprecated code.
 * It's configured to work with PHP 8.3+ and follows Laravel conventions.
 *
 * Note: Minimal configuration to avoid dependency conflicts
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
    )
    ->withPhpSets()
    ->withSkip([
        // Skip directories that should not be refactored
        __DIR__ . '/vendor',
    ]);
