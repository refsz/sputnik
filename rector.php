<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector;

return RectorConfig::configure()
    ->withParallel()
    ->withPaths([__DIR__ . '/src'])
    ->withCache(cacheDirectory: __DIR__ . '/.cache/rector')
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withSkip([
        // DI-injected constructor params/properties look "unused" to Rector
        RemoveUnusedConstructorParamRector::class,
        RemoveUnusedPromotedPropertyRector::class,
    ])
    ->withImportNames(
        importShortClasses: false,
        removeUnusedImports: true,
    );
