<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Command\Addon\Traits;

use Composer\Package\Loader\ArrayLoader;
use Composer\Package\PackageInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Addon\AddonInfo;
use demosplan\DemosPlanCoreBundle\Addon\Composer\PackageInformation;
use demosplan\DemosPlanCoreBundle\Addon\Registrator;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use EFrane\ConsoleAdditions\Batch\Batch;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

trait AddonCommandTrait
{

    abstract protected function getRegistrator(): Registrator;

    protected function removeEntryInAddonsDefinition(AddonInfo $addonInfo, SymfonyStyle $output): void
    {
        $package = $this->loadPackageDefinition($addonInfo);
        $this->getRegistrator()->remove($package);
        $output->info("Addon entry in addons.yml deleted successfully.");
    }

    /**
     * @throws JsonException
     */
    protected function loadPackageDefinition(AddonInfo $addonInfo): PackageInterface
    {
        $loader = new ArrayLoader();
        $installPath = $addonInfo->getInstallPath();
        $composerJsonArray = Json::decodeToArray(
            file_get_contents($installPath . '/composer.json')
        );
        if (!array_key_exists('version', $composerJsonArray)) {
            $composerJsonArray['version'] = PackageInformation::UNDEFINED_VERSION;
        }

        return $loader->load($composerJsonArray);
    }

    protected function removeComposerPackage(AddonInfo $addonInfo, SymfonyStyle $output, bool $clearCache = true): void
    {
        $kernel = $this->getApplication()->getKernel();
        $environment = $kernel->getEnvironment();
        $activeProject = $this->getApplication()
            ->getKernel()
            ->getActiveProject();
        $batchRun = Batch::create($this->getApplication(), $output)
            ->addShell(['composer', 'remove', $addonInfo->getName(), '--working-dir=addons'])
            ->addShell(['composer', 'bin', 'addons', 'update', '-a', '-o', '--prefer-lowest']);
        if($clearCache) {
            // do not warm up cache to avoid errors as the addon is still referenced in the container
            $batchRun->addShell(["bin/{$activeProject}", 'cache:clear', '-e', $environment, '--no-warmup']);
        }
        $batchReturn = $batchRun->run();

        if (0 !== $batchReturn) {
            throw new \RuntimeException('Composer remove failed');
        }
        $output->info("composer package removed successfully.");
    }

    /**
     * @throws IOExceptionInterface
     */
    protected function deleteDirectory(AddonInfo $addonInfo, SymfonyStyle $output): void
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
}
