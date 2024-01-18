<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

require_once __DIR__.'/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'lib',
        'node_modules',
        'services',
        'vendor',
    ]);

$config = new PhpCsFixer\Config();

$config
    ->setRiskyAllowed(true)
    ->setRules(
        [
            'array_syntax'           => ['syntax' => 'short'],
            '@PSR2'                  => true,
            '@Symfony'               => true,
            'binary_operator_spaces' => [
                'operators' => [
                    '=>' => 'align',
                ],
            ],
            'phpdoc_line_span' => [
                'const'    => 'multi', // 'single' or 'multi'
                'property' => 'multi', // 'single' or 'multi'
                'method'   => 'multi',  // 'single' or 'multi'
            ],

            'global_namespace_import' => true,
            'phpdoc_no_alias_tag'     => [
                'replacements' => ['type' => 'var', 'link' => 'see'],
            ],
        ]
    )
    ->setCacheFile('.php-cs-fixer.cache')
    ->setFinder($finder);

return $config;
