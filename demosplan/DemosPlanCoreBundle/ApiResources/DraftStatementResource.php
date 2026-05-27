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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;

#[ApiResource(
    shortName: 'DraftStatement',
    operations: [
        new Get(),
        new GetCollection(),
        new Patch(),
    ],
    routePrefix: '/3.0',
    // provider: DraftStatementStateProvider::class,
    // processor: DraftStatementStateProcessor::class,
)]
class DraftStatementResource
{
    public ?string $id = null;

    public ?array $customFields = null;
}
