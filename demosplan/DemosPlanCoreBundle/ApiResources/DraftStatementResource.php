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
use demosplan\DemosPlanCoreBundle\StateProcessor\DraftStatementPatchProcessor;
use demosplan\DemosPlanCoreBundle\StateProvider\DraftStatementStateProvider;

#[ApiResource(
    shortName: 'DraftStatement',
    operations: [
        new Get(uriTemplate: '/DraftStatement/{id}'),
        new GetCollection(uriTemplate: '/DraftStatement'),
        new Patch(uriTemplate: '/DraftStatement/{id}', processor: DraftStatementPatchProcessor::class),
    ],
    formats: ['jsonapi'],
    routePrefix: ApiPlatformConstants::ROUTE_PREFIX_V3,
    provider: DraftStatementStateProvider::class,
)]
class DraftStatementResource
{
    public ?string $id = null;

    public ?array $customFields = null;
}
