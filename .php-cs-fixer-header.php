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
        'cache',
        'lib',
        'node_modules',
        'services',
        'Soap',
        'var',
        'vendor',
    ]);

$config = new PhpCsFixer\Config();

$header = <<<HEADER
This file is part of the package demosplan.

(c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.

All rights reserved
HEADER;

$config
    ->setRules(
        [
            'header_comment' => [
                'header'       => $header,
                'comment_type' => 'PHPDoc',
                'location'     => 'after_declare_strict',
            ],
        ]
    )
    ->setCacheFile('.php-cs-fixer-header.cache')
    ->setFinder($finder);

return $config;
