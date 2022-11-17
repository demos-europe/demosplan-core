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

use Rector\Core\Configuration\Option;
use Rector\Set\ValueObject\SetList;
use Rector\Symfony\Set\SymfonySetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // Define what rule sets will be applied
    $containerConfigurator->import(SetList::CODE_QUALITY);
    $containerConfigurator->import(SymfonySetList::SYMFONY_44);

    $parameters = $containerConfigurator->parameters();

    $parameters->set(
        Option::SYMFONY_CONTAINER_XML_PATH_PARAMETER,
        '/tmp/bimschgsh/cache/dev/demosplan_DemosPlanCoreBundle_Application_DemosPlanKernelDevDebugContainer.xml'
    );
    $parameters->set(Option::AUTOLOAD_PATHS, [__DIR__.'/../../vendor/autoload.php']);

    $parameters->set(Option::AUTO_IMPORT_NAMES, true);

    // Path to phpstan with extensions, that PHPSTan in Rector uses to determine types
    $parameters->set(Option::PHPSTAN_FOR_RECTOR_PATH, getcwd().'/../../phpstan.neon');
};
