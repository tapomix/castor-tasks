<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in([
        __DIR__ . '/.castor',
        __DIR__ . '/src',
    ])
;

return (new Config())
    ->setRiskyAllowed(true) // required to use "declare_strict_types"
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,

        'attribute_empty_parentheses' => [
            'use_parentheses' => false,
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'declare_strict_types' => true, // ! risky
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => [
            'ignored_tags' => [
                'var',
            ],
        ],
    ])
    // ->setUsingCache(false) # disable cache
;
