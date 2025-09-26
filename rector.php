<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSetsFromTargetPhp(83)
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
    ->withRules([
        // Modern PHP 8.3 features
        AddOverrideAttributeToOverriddenMethodsRector::class,
        
        // Type declarations improvements
        AddVoidReturnTypeWhereNoReturnRector::class,
        TypedPropertyFromStrictConstructorRector::class,
        
        // Code quality improvements
        InlineConstructorDefaultToPropertyRector::class,
        ExplicitBoolCompareRector::class,
        LocallyCalledStaticMethodToNonStaticRector::class,
        
        // Clean up documentation
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        RemoveUselessVarTagRector::class,
    ])
    ->withSkip([
        // Skip directories
        __DIR__ . '/vendor',
        __DIR__ . '/tests/Performance',
        
        // Skip specific rules for certain patterns
        LocallyCalledStaticMethodToNonStaticRector::class => [
            // Keep static factory methods
            __DIR__ . '/src/Models/*',
        ],
        
        ExplicitBoolCompareRector::class => [
            // Keep implicit bool checks in validation contexts
            __DIR__ . '/src/Generator/ValidationRuleMapper.php',
        ],
    ])
    ->withImportNames()
    ->withPreparedSets(
        deadCode: false,
        codeQuality: false,
        typeDeclarations: true,
        earlyReturn: true,
        naming: false,
        privatization: false,
        instanceOf: true
    );