<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

/**
 * Provide a generator for to-be-registered Addon Symfony Bundles.
 */
final class AddonBundleGenerator
{
    /**
     * Enable addons for all environments they can be enabled in
     * if they should be enabled at all.
     */
    public function registerBundles(string $environment): iterable
    {
        $addons = AddonManifestCollection::load();

        foreach ($addons as $addon) {
            if (!array_key_exists('manifest', $addon) || !array_key_exists('entry', $addon['manifest'])) {
                continue;
            }

            $class = $addon['manifest']['entry'];
            if (class_exists($class)) {
                yield new $class($addon['enabled']);
            }
        }
    }
}
