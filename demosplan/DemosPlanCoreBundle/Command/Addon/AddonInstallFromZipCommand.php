<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Addon;

use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\JsonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use ZipArchive;

/**
 * This command handles the whole installation process for a zipped addon bundle.
 * It will create all necessary directories and files if they don't exist and set everything up to have the
 * addon in the right vendor directory and added to composer and the addons.yaml.
 *
 * It does **NOT** handle addon activation!
 */
class AddonInstallFromZipCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:addon:install';
    protected static $defaultDescription = 'Installs an addon based on a given zip-file';

    private string $zipSourcePath;
    private string $zipCachePath;
    private string $addonsDirectory;
    private string $addonsCacheDirectory;

    public function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Path to zip'
        );
    }

    /**
     * The execute function can be interpreted as the basic step-by-step instruction on how to
     * install an addon. All these steps can also be done manually, if necessary.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $this->setPaths($input->getArgument('path'));
        try {
            $this->initializeAddonsInfrastructure();
        } catch (JsonException|RuntimeException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->copyAndUnzipFileIfNecessary();

        try {
            $composerDefinition = $this->getComposerDefinition($this->zipCachePath.'composer.json');
            $this->addAddonToComposerRequire($composerDefinition);
        } catch (JsonException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }

        // Composer clearcache
        try {
            $this->runComposerProcess($output, ['composer', 'clearcache']);
        } catch (ProcessFailedException $e) {
            $output->error($e->getMessage());
        }

        // composer dump-autoload
        try {
            $this->runComposerProcess($output, ['composer', 'dump-autoload']);
        } catch (ProcessFailedException $e) {
            $output->error($e->getMessage());
        }

        // composer update
        try {
            $this->runComposerProcess($output, ['composer', 'bin', 'addons', 'update', '--no-progress']);

            // If composer update went well, add the addon to the registry
            $addonRegistry = new AddonRegistry();
            $addonRegistry->addAddonToRegistry($composerDefinition);

            return Command::SUCCESS;
        } catch (ProcessFailedException $e) {
            $output->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * This method checks if everything necessary for Addons already exists and creates the missing pieces
     * with a default configuration
     *
     * @throws RuntimeException|JsonException
     */
    private function initializeAddonsInfrastructure(): void
    {
        $this->createDirectoryIfNecessary($this->addonsDirectory);
        $this->createDirectoryIfNecessary($this->addonsCacheDirectory);

        // If addons.yaml does not exist, create it
        if (!file_exists($this->addonsDirectory.'addons.yaml')) {
            file_put_contents($this->addonsDirectory.'addons.yaml', Yaml::dump(['addons' => []]));
        }

        // If composer.json does not exist, create it
        if (!file_exists($this->addonsDirectory.'composer.json')) {
            $content = [
                "minimum-stability" => "dev",
                "require" => [],
                "config" => [
                    "sort-packages" => true,
                    "allow-plugins" => [
                        "demos-europe/demosplan-addon-installer" => true,
                    ],
                ],
                "repositories" => [
                    [
                        "type" => "path",
                        "url" => "cache/*",
                        "options" => [
                            "symlink" => true,
                        ],
                    ],
                ],
            ];
            file_put_contents($this->addonsDirectory.'composer.json', Json::encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }

    /**
     * Creates a new directory with the given path if it does not yet exist
     *
     * @throws RuntimeException
     */
    private function createDirectoryIfNecessary(string $directory): void
    {
        if (!file_exists($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }
    }

    /**
     * Sets all necessary paths for the command
     */
    private function setPaths(string $path): void
    {
        $this->addonsDirectory = DemosPlanPath::getRootPath(AddonRegistry::ADDON_DIRECTORY);
        $this->addonsCacheDirectory = DemosPlanPath::getRootPath(AddonRegistry::ADDON_CACHE_DIRECTORY);
        $this->zipSourcePath = DemosPlanPath::getRootPath($path);

        $pathParts = explode('/', $path);
        $fileNameParts = explode('.', $pathParts[count($pathParts)-1]);
        $this->zipCachePath = DemosPlanPath::getRootPath(AddonRegistry::ADDON_CACHE_DIRECTORY.$fileNameParts[0].'/');
    }

    /**
     * This will try to copy and unzip the Repo if the path is correct and the repo is not already present in the cache
     */
    private function copyAndUnzipFileIfNecessary(): void
    {
        $doesFileExist = file_exists($this->zipSourcePath);
        $addonExistsInCache = file_exists($this->zipCachePath);

        if ($doesFileExist && !$addonExistsInCache) {
            $zipArchive = new ZipArchive();
            $open = $zipArchive->open($this->zipSourcePath);
            if ($open) {
                $zipArchive->extractTo($this->addonsCacheDirectory);
            }
        }
    }

    /**
     * Returns the composer.json from the addons cache as an array
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function getComposerDefinition(string $filePath): array
    {
        $composerDefinition = file_get_contents($filePath);

        return Json::decodeToArray($composerDefinition);
    }

    /**
     * Adds the addon name and version to the required part of the composer.json in case
     * the addon is not already present there.
     *
     * @param array $addonComposerDefinition
     *
     * @throws JsonException
     */
    private function addAddonToComposerRequire(array $addonComposerDefinition): void
    {
        $addonName = $addonComposerDefinition['name'];
        $addonVersion = $addonComposerDefinition['version'];

        $composerContent = $this->getComposerDefinition($this->addonsDirectory.'composer.json');

        if (!array_key_exists($addonName, $composerContent['require'])) {
            $composerContent['require'][$addonName] = $addonVersion;
            file_put_contents($this->addonsDirectory.'composer.json', Json::encode($composerContent, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }

    /**
     * Runs the given array as a process
     *
     * @param array<int, string> $command
     */
    private function runComposerProcess(OutputInterface $output, array $command): void
    {
        $composerProcess = new Process($command);
        $output->writeln('Starting composer '.$command[1]);

        $composerProcess->mustRun();
        echo $composerProcess->getOutput();
    }
}
