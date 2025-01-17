<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataCollector;

use demosplan\DemosPlanCoreBundle\Addon\AddonManifestCollectionWrapper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Throwable;

use function compact;

class AddonInfoDataCollector extends DataCollector
{
    public function __construct(private readonly AddonManifestCollectionWrapper $addonManifestCollectionWrapper)
    {
    }

    public function collect(Request $request, Response $response, Throwable $exception = null): void
    {
        $addonsLoaded = $this->addonManifestCollectionWrapper->load();
        $addons = [];
        foreach ($addonsLoaded as $name => $addonLoaded) {
            $addons[] = [
                'name'    => $name,
                'enabled' => $addonLoaded['enabled'] ? 'true' : 'false',
                'version' => $addonLoaded['version'] ?? '-',
            ];
        }

        $this->data = compact('addons');
    }

    public function getName(): string
    {
        return 'app.addon_info_collector';
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getAddons(): array
    {
        return $this->data['addons'];
    }
}
