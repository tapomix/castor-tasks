<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->in(__DIR__ . '/src')
;

return (new Config())
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,

        'attribute_empty_parentheses' => [
            'use_parentheses' => false,
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => [
            'ignored_tags' => [
                'var',
            ],
        ],
    ])
    // ->setUsingCache(false) # disable cache
;
