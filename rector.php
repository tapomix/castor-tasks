<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\SimplifyRegexPatternRector;
use Rector\CodeQuality\Rector\If_\ShortenElseIfRector;
use Rector\CodingStyle\Rector\String_\UseClassKeywordForClassNameResolutionRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\StringableForToStringRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/.castor',
        __DIR__ . '/src',
    ])

    ->withPhpSets()

    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        instanceOf: true,
        typeDeclarations: true,
        privatization: true,
    )

    ->withSkip([
        SimplifyRegexPatternRector::class,
        ShortenElseIfRector::class,
        StringableForToStringRector::class,
        UseClassKeywordForClassNameResolutionRector::class,
    ])
;
