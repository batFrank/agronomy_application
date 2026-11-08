<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->notName('reference.php')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'single_line_after_imports' => false,
        'no_superfluous_phpdoc_tags' => true,
        'single_line_throw' => false,
        'native_function_invocation' => true,
        'declare_strict_types' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'logical_operators' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder)
;
