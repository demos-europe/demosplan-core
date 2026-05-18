<?php

// rector.php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Symfony\Set\TwigSetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withSets([
        // Define what rule sets will be applied
        SetList::CODE_QUALITY,
        SymfonySetList::SYMFONY_64,
        // SymfonyLevelSetList::UP_TO_SYMFONY_54,
        // LevelSetList::UP_TO_PHP_81,
        // TwigSetList::TWIG_UNDERSCORE_TO_NAMESPACE,
        // SensiolabsSetList::ANNOTATIONS_TO_ATTRIBUTES,
        // SensiolabsSetList::ANNOTATIONS_TO_ATTRIBUTES,
        // SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        // DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        // PHPUnitLevelSetList::UP_TO_PHPUNIT_90,
        // DoctrineSetList::DOCTRINE_DBAL_40,
    ])
    ->withAttributesSets(symfony: true)
    ->withSkip([
        // TypedPropertyFromAssignsRector::class,
        // MixedTypeRector::class,
        __DIR__.'/../../config/bundles.php',
    ])
    ->withPaths([__DIR__.'/../../demosplan'])
    ->withPhpVersion(PhpVersion::PHP_81)
    ->withSymfonyContainerXml(
        '/srv/www/var/cache/dev/demosplan_DemosPlanCoreBundle_Application_DemosPlanKernelDevDebugContainer.xml'
    )
    ->withAutoloadPaths([__DIR__.'/../../vendor/autoload.php'])
    ->withImportNames()
    ->withParallel(timeoutSeconds: 180, jobSize: 10);
