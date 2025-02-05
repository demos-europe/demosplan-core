<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Command\CacheClearCommand;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AddonAutoinstallCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:autoinstall';
    protected static $defaultDescription = 'Installs any addons defined in project configuration addons.yaml';

    public function __construct(
        private readonly AddonInstallFromZipCommand $addonInstallCommand,
        private readonly AddonUninstallCommand $addonUninstallCommand,
        private readonly AddonRegistry $registry,
        private readonly CacheClearCommand $cacheClearCommand,
        ParameterBagInterface $parameterBag,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $addons = $this->parameterBag->get('dplan_addons');
        // return if no addons are defined
        if (null === $addons) {
            return Command::SUCCESS;
        }

        $enabledAddons = $this->registry->getEnabledAddons();

        // remove already installed addons with the correct versions from the list
        foreach ($addons as $key => $addonConfig) {
            $name = $addonConfig['name'];
            $repo = 'demos-europe/'.$name;
            if (array_key_exists($repo, $enabledAddons)) {
                if ($addonConfig['version'] === $enabledAddons[$repo]->getVersion()) {
                    $output->note("Addon {$name} is already installed in Version {$addonConfig['version']}");
                    unset($addons[$key]);
                } else {
                    // uninstall addon if it is installed but in the wrong version
                    $this->uninstallOutdatedAddon($output, $name, $enabledAddons[$repo], $addonConfig['version']);
                }
                // unset from enabled addons to find addons that are enabled but not in the config and thus need to be uninstalled
                unset($enabledAddons[$repo]);
            }
        }

        // uninstall addons that are enabled but not in the config
        $this->uninstallUnneededAddons($enabledAddons, $output);

        // iterate over addons and install them if they are not already installed
        $this->installAddons($addons, $enabledAddons, $output);

        if (!empty($addons)) {
            $output->note('This command will end with an error because during the installation of the addons the cache is cleared. There seems to be no way to avoid this. When it states that some cache file was not found, everything is fine.');
        }

        return Command::SUCCESS;
    }

    private function runCommand(Command $command, array $arguments, SymfonyStyle $output): int
    {
        $arrayInput = new ArrayInput($arguments);
        $command->setApplication($this->getApplication());

        return $command->run($arrayInput, $output);
    }

    private function installAddons(mixed $addons, array $enabledAddons, SymfonyStyle $output): void
    {
        foreach ($addons as $addonConfig) {
            $name = $addonConfig['name'];
            if (!in_array($name, $enabledAddons, true)) {
                $output->note("Installing addon {$name} in Version {$addonConfig['version']}");

                $arguments = [
                    '--name'   => $name,
                    '--tag'    => $addonConfig['version'],
                    '--github' => true,
                ];
                try {
                    $this->runCommand($this->addonInstallCommand, $arguments, $output);
                } catch (\Exception $e) {
                    $output->error("Failed to install addon {$name} in Version {$addonConfig['version']}. {$e->getMessage()}");
                }
            }
        }
    }

    private function uninstallUnneededAddons(array $enabledAddons, SymfonyStyle $output): void
    {
        foreach ($enabledAddons as $repo => $addon) {
            $output->note("Addon {$addon->getName()} is enabled but not in the config and will be uninstalled");
            $arguments = [
                'name' => $repo,
            ];
            $this->runCommand($this->addonUninstallCommand, $arguments, $output);
        }
    }

    private function uninstallOutdatedAddon(SymfonyStyle $output, mixed $name, $enabledAddons, $version): void
    {
        $output->note("Addon {$name} is already installed in Version {$enabledAddons->getVersion()}, but should be in Version {$version}");
        $arguments = [
            'name' => sprintf('demos-europe/%s', $name),
        ];
        $this->runCommand($this->addonUninstallCommand, $arguments, $output);
    }
}
