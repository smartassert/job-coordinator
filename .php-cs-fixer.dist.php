<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/bin')
    ->in(__DIR__ . '/public')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@PhpCsFixer' => true,
        'concat_space' => [
            'spacing' => 'one',
        ],
        'php_unit_internal_class' => false,
        'php_unit_test_class_requires_covers' => false,
        'declare_strict_types' => true,
        'types_spaces' => [
            'space' => 'none',
            'space_multiple_catch' => 'single',
        ],
    ])
    ->setFinder($finder)
    ;
