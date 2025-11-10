<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        'yoda_style' => false,
        'native_function_invocation' => false, // Disable backslash prefix
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'php_unit_internal_class' => false, // Disable @internal annotation in tests
        'php_unit_test_class_requires_covers' => false, // Disable @covers/@coversNothing requirement
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder)
;
