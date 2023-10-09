<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Addon;

/**
 * Helper class to be able to test Classes that use the static method
 * AddonManifestCollection::load()
 */
class AddonManifestCollectionWrapper {
    public function load(): array {
        return AddonManifestCollection::load();
    }
}
