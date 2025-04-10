<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Exception\AddonException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Symfony\Component\Yaml\Yaml;

final class FrontendAssetProvider
{
    public function __construct(private readonly PermissionsInterface $permissions, private readonly AddonRegistry $registry)
    {
    }

    /**
     * @return array<string, array<string, mixed>>>
     */
    public function getFrontendClassesForHook(string $hookName): array
    {
        $assetList = array_map(function (AddonInfo $addonInfo) use ($hookName) {
            if (!$addonInfo->isEnabled() || !$addonInfo->hasUIHooks()) {
                return [];
            }

            $uiData = $addonInfo->getUIHooks();

            if (!array_key_exists($hookName, $uiData['hooks'])) {
                return [];
            }

            $hookData = $uiData['hooks'][$hookName];
            $manifestPath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/'.$uiData['manifest'];

            // Return if no access granted for that addon at that entrypoint
            if (0 !== count(array_filter($hookData['options']['permissions'], fn (array $permission) => $this->permissions->hasPermission[$permission]))) {
                return [];
            }

            try {
                $entries = $this->getAssetPathsFromManifest($manifestPath, $hookData['entry']);

                if (!array_key_exists('js', $entries)) {
                    throw new AddonException('Entry has no javascript and is thus pretty much useless');
                }

                $assetContents = [];

                foreach ($entries['js'] as $entry) {
                    // Try to get the content of the actual asset
                    $entryFilePath = DemosPlanPath::getRootPath($addonInfo->getInstallPath()).'/dist/'.$entry;
                    // uses local file, no need for flysystem
                    $assetContents[$entry] = file_get_contents($entryFilePath);
                }

                if (0 === count($assetContents)) {
                    return [];
                }
            } catch (AddonException) {
                return [];
            }

            return $this->createAddonFrontendAssetsEntry($hookData, $assetContents);
        }, $this->registry->getAddonInfos());

        // avoid exposing addon information unnecessarily
        return array_filter($assetList, fn (array $assetInfo) => 0 !== count($assetInfo));
    }

    /**
     * @param array<string, string|array> $hookData
     * @param array<string, string>       $assetContents
     *
     * @return array<string, array{entry:string, options:array, content:string}>
     */
    private function createAddonFrontendAssetsEntry(array $hookData, array $assetContents): array
    {
        return [
            'entry'   => $hookData['entry'],
            'options' => $hookData['options'],
            'content' => $assetContents,
        ];
    }

    /**
     * Get the asset dictionary of an entry.
     *
     * Returns a dictionary of assets mapped by file type (i.e. ['js' => ['asset.js']])
     *
     * @return array<string,mixed>
     *
     * @throws AddonException
     */
    private function getAssetPathsFromManifest(string $manifestPath, string $entryName): array
    {
        // uses local file, no need for flysystem
        if (!file_exists($manifestPath)) {
            AddonException::invalidManifest($manifestPath);
        }

        $manifestContent = Yaml::parseFile($manifestPath);

        if (!array_key_exists($entryName, $manifestContent['entrypoints'])) {
            AddonException::manifestEntryNotFound($entryName);
        }

        return $manifestContent['entrypoints'][$entryName]['assets'];
    }
}
