<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ApiResources;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use demosplan\DemosPlanCoreBundle\StateProvider\ClaimStateProvider;

#[ApiResource(
    operations: [
        new Get(),
    ],
    routePrefix: '/3.0',
    provider: ClaimStateProvider::class,
    // processor: ClaimStateProcessor::class
)]
class ClaimResource
{
    public ?string $id = null;

    public ?string $name = null;

    public ?string $orgaName = null;
}
