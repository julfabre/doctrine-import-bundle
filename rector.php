<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
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
    ->withSkip([PreferPHPUnitThisCallRector::class])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
;
