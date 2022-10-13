<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

require_once __DIR__.'/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
    ->in(DemosPlanPath::getRootPath())
    ->exclude([
        'demosplan/DemosPlanCoreBundle/DoctrineMigrations', // to be discussed!
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
            'phpdoc_no_alias_tag'    => [
                'replacements' => ['type' => 'var', 'link' => 'see'],
            ],
        ]
    )
    ->setCacheFile('.php-cs-fixer.cache')
    ->setFinder($finder);

return $config;
