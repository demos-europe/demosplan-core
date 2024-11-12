<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use Composer\Console\Input\InputOption;
use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Addon\AddonInfo;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Addon\Composer\PackageInformation;
use demosplan\DemosPlanCoreBundle\Addon\Registrator;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Helper\QuestionHelper;
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
        ?string $name = null,
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
        $this->addOption('all', 'a', InputOption::VALUE_NONE, 'Uninstall all Addons');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $addonsInfos = $this->registry->getAddonInfos();
        $all = $input->getOption('all');

        if (empty($addonsInfos)) {
            $output->info('No addons installed, nothing to uninstall');

            return self::SUCCESS;
        }

        // Handle the --all option
        if ($all) {
            foreach ($addonsInfos as $addonInfo) {
                $this->uninstallAddon($addonInfo, $output);
            }

            // clear cache
            $this->clearCache($output);
            $output->success('All addons successfully uninstalled.');

            return self::SUCCESS;
        }

        $name = $input->getArgument('name');

        if (null === $name) {
            // get a list of installed addons and let user choose
            $addons = array_values(array_map(
                static fn ($addonInfo) => $addonInfo->getName(), $addonsInfos
            ));
            $question = new ChoiceQuestion('Which addon do you want to uninstall? ', $addons);
            /** @var QuestionHelper $questionHelper */
            $questionHelper = $this->getHelper('question');
            $name = $questionHelper->ask($input, $output, $question);
        }

        if (!array_key_exists($name, $addonsInfos)) {
            $output->error("Addon $name not found");

            return self::FAILURE;
        }

        $output->info("Uninstalling addon {$name}...");

        $addonInfo = $addonsInfos[$name];

        $this->uninstallAddon($addonInfo, $output);

        // clear cache
        $this->clearCache($output);

        $output->success("Addon {$name} successfully uninstalled");

        return self::SUCCESS;
    }

    private function uninstallAddon(AddonInfo $addonInfo, SymfonyStyle $output)
    {
        $output->info("Uninstalling addon {$addonInfo->getName()}...");

        try {
            // remove entry in addons.yml
            $this->removeEntryInAddonsDefinition($addonInfo, $output);
            // remove files at install_path
            $this->deleteDirectory($addonInfo, $output);
            // run composer remove <name>
            $this->removeComposerPackage($addonInfo, $output);
        } catch (IOExceptionInterface $e) {
            $output->error('An error occurred while deleting the directory at '.$e->getPath().': '.$e->getMessage().'.');

            return self::FAILURE;
        } catch (JsonException $e) {
            $output->error('An error occurred while loading the package definition: '.$e->getMessage().'.');

            return self::FAILURE;
        } catch (Exception $e) {
            $output->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @throws JsonException
     */
    private function loadPackageDefinition(AddonInfo $addonInfo): PackageInterface
    {
        $loader = new ArrayLoader();
        $installPath = $addonInfo->getInstallPath();
        // uses local file, no need for flysystem
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
        // local file only, no need for flysystem
        $filesystem = new Filesystem();
        // remove files in symlinked target if they exist
        $symlinkedPath = $filesystem->readlink($installPath, true);
        $pathInfo = new SplFileInfo($installPath);
        $cachePath = DemosPlanPath::getRootPath(Registrator::ADDON_CACHE_DIRECTORY.$pathInfo->getBasename());
        $symlinkedCachePath = $filesystem->readlink($cachePath, true);
        // do not delete files in symlinked target if they are symlinked from somewhere else
        if ((null !== $symlinkedPath) && null === $symlinkedCachePath) {
            // addon is installed regularly, remove it entirely
            $filesystem->remove($symlinkedPath);
        } else {
            // remove cache symlink to dev directory
            // local file is valid, no need for flysystem
            unlink($cachePath);
        }
        $filesystem->remove($installPath);
        $output->info('Addon successfully deleted from cache directory.');
    }

    private function removeEntryInAddonsDefinition(AddonInfo $addonInfo, SymfonyStyle $output): void
    {
        $package = $this->loadPackageDefinition($addonInfo);
        $this->registrator->remove($package);
        $output->info('Addon entry in addons.yml deleted successfully.');
    }

    private function removeComposerPackage(AddonInfo $addonInfo, SymfonyStyle $output): void
    {
        $batchReturn = Batch::create($this->getApplication(), $output)
            ->addShell(['composer', 'remove', $addonInfo->getName(), '--working-dir=addons'])
            ->addShell(['composer', 'bin', 'addons', 'update', '-a', '-o', '--prefer-lowest'])
            ->run();

        if (0 !== $batchReturn) {
            throw new RuntimeException('Composer remove failed');
        }
        $output->info('composer package removed successfully.');
    }

    private function clearCache(SymfonyStyle $output): void
    {
        $kernel = $this->getApplication()->getKernel();
        $environment = $kernel->getEnvironment();
        /** @var DemosPlanKernel $kernel */
        $activeProject = $kernel->getActiveProject();
        // do not warm up cache to avoid errors as the addon is still referenced in the container
        $cacheClearCommand = ["bin/{$activeProject}", 'cache:clear', '-e', $environment, '--no-warmup', " && dp d:deploy {$activeProject} -ssync"];

        $batchReturn = Batch::create($this->getApplication(), $output)
            ->addShell($cacheClearCommand)
            ->run();

        if (0 !== $batchReturn) {
            throw new RuntimeException('Cache clear failed');
        }

        $output->info('Cache successfully cleared.');
    }
}
