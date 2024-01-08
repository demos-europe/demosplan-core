<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Addon\AddonInfo;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Addon\Composer\PackageInformation;
use demosplan\DemosPlanCoreBundle\Addon\Registrator;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

class AddonUninstallCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:uninstall';
    protected static $defaultDescription = 'Uninstall installed addons';

    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly Registrator $registrator,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL,
            'Name of the addon to uninstall. May be omitted to receive a list of installed addons.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $addonsInfos = $this->registry->getAddonInfos();

        if (empty($addonsInfos)) {
            $output->info("No addons installed, nothing to uninstall");

            return self::SUCCESS;
        }

        $name = $input->getArgument('name');

        if (null === $name) {
            // get a list of installed addons and let user choose
            $addons = array_values(array_map(
                static fn ($addonInfo) => $addonInfo->getName(), $addonsInfos
            ));
            $question = new ChoiceQuestion('Which addon do you want to uninstall? ', $addons);
            $name = $this->getHelper('question')->ask($input, $output, $question);
        }

        if(!array_key_exists($name, $addonsInfos)) {
            $output->error("Addon $name not found");

            return self::FAILURE;
        }

        $output->info("Uninstalling addon {$name}...");

        $addonInfo = $addonsInfos[$name];

        try {
            // remove entry in addons.yml
            $this->removeEntryInAddonsDefinition($addonInfo, $output);
            // remove files at install_path
            $this->deleteDirectory($addonInfo, $output);
            // run composer remove <name>
            $this->removeComposerPackage($addonInfo, $output);

        } catch (IOExceptionInterface $e) {
            $output->error("An error occurred while deleting the directory at ".
                $e->getPath().": ".$e->getMessage().".");

            return self::FAILURE;
        } catch (JsonException $e) {
            $output->error("An error occurred while loading the package definition: ".
                $e->getMessage().".");

            return self::FAILURE;
        } catch (Exception $e) {
            $output->error($e->getMessage());

            return self::FAILURE;
        }

        $output->success("Addon {$name} successfully uninstalled");

        return self::SUCCESS;
    }
    /**
     * @throws JsonException
     */
    private function loadPackageDefinition(AddonInfo $addonInfo): PackageInterface
    {
        $loader = new ArrayLoader();
        $installPath = $addonInfo->getInstallPath();
        $composerJsonArray = Json::decodeToArray(file_get_contents($installPath.'/composer.json'));
        if (!array_key_exists('version', $composerJsonArray)) {
            $composerJsonArray['version'] = PackageInformation::UNDEFINED_VERSION;
        }

        return $loader->load($composerJsonArray);
    }
    /**
     * @throws IOExceptionInterface
     */
    private function deleteDirectory(AddonInfo $addonInfo, SymfonyStyle $output): void
    {
        $installPath = $addonInfo->getInstallPath();
        $filesystem = new Filesystem();
        // remove files in symlinked target if they exist
        $symlinkedPath = $filesystem->readlink($installPath, true);
        if (null !== $symlinkedPath) {
            $filesystem->remove($symlinkedPath);
        }
        $filesystem->remove($installPath);
        $output->info("Addon successfully deleted from cache directory.");
    }

    private function removeEntryInAddonsDefinition(AddonInfo $addonInfo, SymfonyStyle $output): void
    {
        $package = $this->loadPackageDefinition($addonInfo);
        $this->registrator->remove($package);
        $output->info("Addon entry in addons.yml deleted successfully.");
    }

    private function removeComposerPackage(AddonInfo $addonInfo, SymfonyStyle $output): void
    {
        $kernel = $this->getApplication()->getKernel();
        $environment = $kernel->getEnvironment();
        $activeProject = $this->getApplication()->getKernel()->getActiveProject();
        $batchReturn = Batch::create($this->getApplication(), $output)
            ->addShell(['composer', 'remove', $addonInfo->getName(), '--working-dir=addons'])
            ->addShell(['composer', 'bin', 'addons', 'update', '-a', '-o', '--prefer-lowest'])
            // do not warm up cache to avoid errors as the addon is still referenced in the container
            ->addShell(["bin/{$activeProject}", 'cache:clear', '-e', $environment, '--no-warmup'])
            ->run();

        if (0 !== $batchReturn) {
            throw new \RuntimeException('Composer remove failed');
        }
        $output->info("composer package removed successfully.");
    }
}
