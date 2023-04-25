<?php

// rector.php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonyLevelSetList;

return static function (RectorConfig $rectorConfig): void {
    // Define what rule sets will be applied
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SymfonyLevelSetList::UP_TO_SYMFONY_44,
//        SymfonyLevelSetList::UP_TO_SYMFONY_54,
//        LevelSetList::UP_TO_PHP_74,
//        LevelSetList::UP_TO_PHP_80,
    ]);
    $rectorConfig->paths([__DIR__ . '/../../demosplan']);

    $rectorConfig->symfonyContainerXml(
        '/tmp/diplanbau/cache/dev/demosplan_DemosPlanCoreBundle_Application_DemosPlanKernelDevDebugContainer.xml'
    );
    $rectorConfig->autoloadPaths([__DIR__.'/../../vendor/autoload.php']);
    $rectorConfig->importNames();
    $rectorConfig->disableParallel();
    // Path to phpstan with extensions, that PHPSTan in Rector uses to determine types
    //$rectorConfig->phpstanConfig('/srv/www/phpstan.neon');
};
