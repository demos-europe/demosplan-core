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
use Rector\Core\ValueObject\PhpVersion;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\FunctionLike\UnionTypesRector;
use Rector\PHPUnit\Set\PHPUnitLevelSetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SensiolabsSetList;
use Rector\Symfony\Set\SymfonyLevelSetList;
use Rector\Symfony\Set\TwigLevelSetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;

return static function (RectorConfig $rectorConfig): void {
    // Define what rule sets will be applied
    $rectorConfig->sets([
//        SetList::CODE_QUALITY,
//        SymfonyLevelSetList::UP_TO_SYMFONY_44,
        // SymfonyLevelSetList::UP_TO_SYMFONY_54,
        // LevelSetList::UP_TO_PHP_53,
//        LevelSetList::UP_TO_PHP_74,
        LevelSetList::UP_TO_PHP_81,
        // TwigLevelSetList::UP_TO_TWIG_240,
        // SensiolabsSetList::ANNOTATIONS_TO_ATTRIBUTES,
        // PHPUnitLevelSetList::UP_TO_PHPUNIT_90,
        // DoctrineSetList::DOCTRINE_DBAL_40,
    ]);
    $rectorConfig->skip([
        TypedPropertyFromAssignsRector::class,
        UnionTypesRector::class,
        __DIR__.'/../../config/bundles.php',
    ]);
    // $rectorConfig->paths([__DIR__ . '/../../demosplan']);

    $rectorConfig->phpVersion(PhpVersion::PHP_81);
    $rectorConfig->symfonyContainerXml(
        '/tmp/diplanbau/cache/dev/demosplan_DemosPlanCoreBundle_Application_DemosPlanKernelDevDebugContainer.xml'
    );
    $rectorConfig->autoloadPaths([__DIR__.'/../../vendor/autoload.php']);
    // $rectorConfig->importNames();
    // $rectorConfig->disableParallel();
    $rectorConfig->parallel(seconds: 180, jobSize: 10);
    // Path to phpstan with extensions, that PHPSTan in Rector uses to determine types
    $rectorConfig->phpstanConfig(__DIR__.'/../../vendor/phpstan/phpstan-symfony/extension.neon');
};
