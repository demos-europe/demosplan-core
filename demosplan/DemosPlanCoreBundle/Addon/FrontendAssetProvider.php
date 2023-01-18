<?php

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Yaml\Yaml;

final class FrontendAssetProvider
{
    private AddonRegistry $registry;

    public function __construct(AddonRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * @return array<string, array<string, mixed>>>
     */
    public function getFrontendClassesForHook(string $hookName): array
    {
        return array_map(function (AddonInfo $addonInfo) use ($hookName) {
            if (!$addonInfo->isEnabled() || !$addonInfo->hasUIHooks()) {
                return [];
            }

            $uiData = $addonInfo->getUIHooks();

            if (!array_key_exists($hookName, $uiData['hooks'])) {
                return [];
            }

            $hookData = $uiData['hooks'][$hookName];
            $manifestPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath().'/'.$uiData['manifest']);

            try {
                $entryFile = $this->getAssetPathFromManifest($manifestPath, $hookData['entry']);
                // Try to get the content of the actual asset
                $entryFilePath = DemosPlanPath::getRootPath($addonInfo->getInstallPath().'/'.$entryFile);
                $assetContent = file_get_contents($entryFilePath);
                if (!$assetContent) {
                    return [];
                }
            } catch (AddonException $e) {
                return [];
            }

            return $this->createAddonFrontendAssetsEntry($addonInfo->getName(), $hookData, $assetContent);
        }, iterator_to_array($this->registry));
    }

    /**
     * @param array<string, string|array> $hookData
     *
     * @return array<string, array{entry:string, options:array, content:string}>
     */
    private function createAddonFrontendAssetsEntry(string $addonName, array $hookData, string $assetContent): array
    {
        return [
            $addonName => [
                'entry'   => $hookData['entry'],
                'options' => $hookData['options'],
                'content' => $assetContent,
            ],
        ];
    }

    /**
     * @throws AddonException
     */
    private function getAssetPathFromManifest(string $manifestPath, string $entryName): string
    {
        if (!file_exists($manifestPath)) {
            AddonException::invalidManifest($manifestPath);
        }

        $manifestContent = Yaml::parseFile($manifestPath);

        if (!array_key_exists($entryName, $manifestContent)) {
            AddonException::manifestEntryNotFound($entryName);
        }

        return $manifestContent[$entryName];
    }
}
