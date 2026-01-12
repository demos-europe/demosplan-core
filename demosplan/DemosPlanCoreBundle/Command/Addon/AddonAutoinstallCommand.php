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
use demosplan\DemosPlanCoreBundle\Addon\Registrator;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:addon:autoinstall', description: 'Installs any addons defined in project configuration addons.yaml')]
class AddonAutoinstallCommand extends CoreCommand
{
    public function __construct(
        private readonly AddonInstallFromZipCommand $addonInstallCommand,
        private readonly AddonUninstallCommand $addonUninstallCommand,
        private readonly AddonRegistry $registry,
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
        $changesWereMade = false;
        $installedAddonNames = [];

        // Phase 1: Identify and uninstall outdated/unneeded addons (without cache clear)
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
                    $changesWereMade = true;
                }
                // unset from enabled addons to find addons that are enabled but not in the config
                unset($enabledAddons[$repo]);
            }
        }

        // Uninstall addons that are enabled but not in the config (without cache clear)
        if (!empty($enabledAddons)) {
            $this->uninstallUnneededAddons($enabledAddons, $output);
            $changesWereMade = true;
        }

        // Phase 2: Install new addons (without cache clear and frontend build)
        if (!empty($addons)) {
            $installedAddonNames = $this->installAddons($addons, $output);
            $changesWereMade = true;
        }

        // Phase 3: Single cache clear if any changes were made
        if ($changesWereMade) {
            $output->section('Clearing cache...');
            $this->clearCache($output);
        }

        // Phase 4: Build frontends for addons that need it (after cache is cleared)
        if (!empty($installedAddonNames)) {
            $this->buildFrontends($installedAddonNames, $output);
        }

        if ($changesWereMade) {
            $output->success('Addon autoinstall completed successfully.');
        }

        return Command::SUCCESS;
    }

    private function runCommand(Command $command, array $arguments, SymfonyStyle $output): int
    {
        $arrayInput = new ArrayInput($arguments);
        $command->setApplication($this->getApplication());

        return $command->run($arrayInput, $output);
    }

    /**
     * @return array<string> List of installed addon names (for frontend build tracking)
     */
    private function installAddons(array $addons, SymfonyStyle $output): array
    {
        $installedNames = [];

        foreach ($addons as $addonConfig) {
            $name = $addonConfig['name'];
            $output->note("Installing addon {$name} in Version {$addonConfig['version']}");

            $arguments = [
                '--name'                => $name,
                '--tag'                 => $addonConfig['version'],
                '--github'              => true,
                '--skip-cache-clear'    => true,
                '--skip-frontend-build' => true,
            ];
            try {
                $result = $this->runCommand($this->addonInstallCommand, $arguments, $output);
                if (Command::SUCCESS === $result) {
                    $installedNames[] = $name;
                }
            } catch (Exception $e) {
                $output->error("Failed to install addon {$name} in Version {$addonConfig['version']}. {$e->getMessage()}");
            }
        }

        return $installedNames;
    }

    private function uninstallUnneededAddons(array $enabledAddons, SymfonyStyle $output): void
    {
        foreach ($enabledAddons as $repo => $addon) {
            $output->note("Addon {$addon->getName()} is enabled but not in the config and will be uninstalled");
            $arguments = [
                'name'               => $repo,
                '--skip-cache-clear' => true,
            ];
            $this->runCommand($this->addonUninstallCommand, $arguments, $output);
        }
    }

    private function uninstallOutdatedAddon(SymfonyStyle $output, string $name, $enabledAddon, string $version): void
    {
        $output->note("Addon {$name} is already installed in Version {$enabledAddon->getVersion()}, but should be in Version {$version}");
        $arguments = [
            'name'               => sprintf('demos-europe/%s', $name),
            '--skip-cache-clear' => true,
        ];
        $this->runCommand($this->addonUninstallCommand, $arguments, $output);
    }

    private function clearCache(SymfonyStyle $output): void
    {
        $kernel = $this->getApplication()->getKernel();
        $environment = $kernel->getEnvironment();
        /** @var DemosPlanKernel $kernel */
        $activeProject = $kernel->getActiveProject();

        $batchReturn = Batch::create($this->getApplication(), $output)
            ->addShell(['bin/console', 'cache:clear', '-e', $environment], null, ['ACTIVE_PROJECT' => $activeProject])
            ->run();

        if (0 !== $batchReturn) {
            $output->warning('Cache clear may have had issues, but continuing...');
        }
    }

    private function buildFrontends(array $addonNames, SymfonyStyle $output): void
    {
        $kernel = $this->getApplication()->getKernel();
        $environment = $kernel->getEnvironment();
        /** @var DemosPlanKernel $kernel */
        $activeProject = $kernel->getActiveProject();

        $addonsCacheDir = DemosPlanPath::getRootPath(Registrator::ADDON_CACHE_DIRECTORY);

        foreach ($addonNames as $addonName) {
            // Check if addon has frontend assets by looking for package.json in the cache directory
            // The addon folder name follows the pattern: {name}-{version} (without 'v' prefix)
            $addonFolders = glob($addonsCacheDir.$addonName.'*');

            foreach ($addonFolders as $addonFolder) {
                if (file_exists($addonFolder.'/package.json')) {
                    $output->note("Building frontend for addon {$addonName}...");

                    $batchReturn = Batch::create($this->getApplication(), $output)
                        ->addShell(
                            ['bin/console', 'dplan:addon:build-frontend', 'demos-europe/'.$addonName, '-e', $environment],
                            null,
                            ['ACTIVE_PROJECT' => $activeProject]
                        )
                        ->run();

                    if (0 !== $batchReturn) {
                        $output->warning("Frontend build for {$addonName} may have had issues.");
                    }
                    break; // Only build once per addon
                }
            }
        }
    }
}
