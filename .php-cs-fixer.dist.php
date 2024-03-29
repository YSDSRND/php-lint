<?php

$finder = PhpCsFixer\Finder::create()
    ->path([
        'src/',
        'tests/',
    ])
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->registerCustomFixers([
        new \YSDS\Lint\ReplaceStringsFixer(),
    ])
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR1' => true,
        '@PSR2' => true,
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'declare_strict_types' => true,
        'method_argument_space' => [
            'keep_multiple_spaces_after_comma' => false,
            'on_multiline' => 'ignore',
        ],
        'no_trailing_comma_in_list_call' => true,
        'no_trailing_whitespace_in_comment' => false,
        'trailing_comma_in_multiline' => true,

        'YSDS/replace_strings' => [
            'fix_common' => true,
        ],
    ])
    ->setFinder($finder);
