<?php

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

require_once __DIR__.'/vendor/autoload.php';

$finder = PhpCsFixer\Finder::create()
    ->in(DemosPlanPath::getRootPath())
    ->exclude([
        'demosplan/DemosPlanCoreBundle/DoctrineMigrations', // to be discussed!
        'documentation',
        'infrastructure',
        'lib',
        'node_modules',
        'projects', // to be discussed!
        'services',
        'tests', // to be discussed!
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
