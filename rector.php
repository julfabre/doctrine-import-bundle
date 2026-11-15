<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests', __DIR__.'/config', __DIR__.'/tools'])
    ->withCache(__DIR__.'/var/cache/rector')
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
        symfonyCodeQuality: true,
        doctrineCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, doctrine: true, phpunit: true)
    // Static assertion calls (self::assert*()) are the project convention, enforced by PHP-CS-Fixer.
    ->withSkip([
        __DIR__.'/tests/Fixtures',
        PreferPHPUnitThisCallRector::class,
        // The rendering layer names the classes of the generated code: they are not runtime dependencies of the bundle.
        StringClassNameToClassConstantRector::class => [__DIR__.'/src/Rendering', __DIR__.'/tests/Unit/Rendering', __DIR__.'/tests/Integration/Rendering'],
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
;
