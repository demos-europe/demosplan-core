<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use demosplan\DemosPlanCoreBundle\Addon\AddonManifestCollectionWrapper;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AddonListCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:list';
    protected static $defaultDescription = 'List installed addons';

    public function __construct(
        private readonly AddonManifestCollectionWrapper $addonManifestCollectionWrapper,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $addons = [];
        $addonsLoaded = $this->addonManifestCollectionWrapper->load();

        foreach ($addonsLoaded as $name => $addonLoaded) {
            $addons[] = [
                'name'    => $name,
                'enabled' => $addonLoaded['enabled'] ? 'true' : 'false',
                'version' => $addonLoaded['version'] ?? '-',
            ];
        }

        // Create a Table instance
        $table = new Table($output);

        // Set the table headers
        $table->setHeaders(['Name', 'Enabled', 'Version']);

        // Add rows to the table
        $table->setRows($addons);

        // Render the table
        $table->render();

        return self::SUCCESS;
    }
}
