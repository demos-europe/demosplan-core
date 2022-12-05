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

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ZipArchive;

class AddonInstallFromZipCommand extends CoreCommand
{
    public const ADDON_CACHE_PATH = '/addons/cache/';
    protected static $defaultName = 'dplan:addon:install';
    protected static $defaultDescription = 'Installs an addon based on a given zip-file';

    public function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Path to zip'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');

        // 1. Check if path to zip is correct and there is a zip there.
        $isUnzippingNecessary = $this->isZipExtractionNecessary($path);
        // 2. Unpack the zip to the persistent addons cache: addons/cache
        if ($isUnzippingNecessary) {
            $this->copyAndUnzipFile($path);
        }
        // 3. Read in the composer.json
        // 4. copy the content of the zip file to: addons/vendor/<name> if that directory does not yet exist. Otherwise prompt exception that addon already exists
        // 5. If the psr4-autoload entry does not yet exist in the addons.yaml, add it with enabled:false
        // 6. Do a 'composer clearcache'
        // 7. Do a 'composer dump-autoload'
        // 8. Do a 'composer update <addon name>'

        return 0;
    }

    /**
     * This will try to copy and unzip the Repo if the path is correct and the repo is not already present in the cache
     */
    private function copyAndUnzipFile(string $path): void
    {
        $zipArchive = new ZipArchive();
        $open = $zipArchive->open($path);
        if ($open) {
            $zipArchive->extractTo(self::ADDON_CACHE_PATH);
        }
    }

    /**
     * We only need to handle the zip extraction if the zip exists and is not already in the addon cache
     */
    private function isZipExtractionNecessary(string $path): bool
    {
        $pathParts = explode('/', $path);
        $doesFileExist = file_exists(DemosPlanPath::getRootPath($path));
        $addonExistsInCache = file_exists(DemosPlanPath::getRootPath(self::ADDON_CACHE_PATH).$pathParts[count($pathParts)-1]);

        return $doesFileExist && !$addonExistsInCache;
    }

}
