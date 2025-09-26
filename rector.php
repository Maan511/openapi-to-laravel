<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

/**
 * Rector configuration for automated PHP refactoring
 *
 * This configuration enables modern PHP patterns and removes deprecated code.
 * It's configured to work with PHP 8.3+ and follows Laravel conventions.
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSets([
        // Upgrade to PHP 8.3 features
        LevelSetList::UP_TO_PHP_83,
    ])
    ->withRules([
        // Example rules for demonstration

        // 1. Type declarations improvements
        AddVoidReturnTypeWhereNoReturnRector::class,

        // 2. Code quality improvements (example rule)
        InlineConstructorDefaultToPropertyRector::class,

        // 3. Clean up documentation (removes redundant PHPDoc tags)
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
    ])
    ->withSkip([
        // Skip directories that should not be refactored
        __DIR__ . '/vendor',
        __DIR__ . '/tests/Performance',

        // Skip files with complex validation logic to preserve current behavior
        __DIR__ . '/src/Generator/ValidationRuleMapper.php',

        // Example: Skip specific rules for certain files
        InlineConstructorDefaultToPropertyRector::class => [
            // Skip for classes that need explicit constructor logic
            __DIR__ . '/src/Console/GenerateFormRequestsCommand.php',
        ],
    ]);
