<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = (new Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude(['vendor', '.cache']);

return (new Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRiskyAllowed(true)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.cache/php_cs_fixer.cache')
    ->setFinder($finder)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'concat_space' => ['spacing' => 'one'],
        'declare_strict_types' => true,
        'fully_qualified_strict_types' => ['import_symbols' => true, 'leading_backslash_in_global_namespace' => true],
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_class_elements' => true,
        'phpdoc_line_span' => true,
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        'single_line_throw' => false,
        'strict_comparison' => true,
        'strict_param' => true,
        'trailing_comma_in_multiline' => [
            'after_heredoc' => true,
            'elements' => [
                'arguments',
                'array_destructuring',
                'arrays',
                'match',
                'parameters',
            ],
        ],
        'void_return' => true,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ]);
