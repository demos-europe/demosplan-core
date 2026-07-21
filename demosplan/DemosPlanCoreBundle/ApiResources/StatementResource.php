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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementStateProvider;

#[ApiResource(
    shortName: 'Statement',
    operations: [new Get(uriTemplate: '/Statement/{id}')],
    formats: ['jsonapi'],
    routePrefix: '/3.0',
    provider: StatementStateProvider::class,
)]
class StatementResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id = '';

    #[ApiProperty(readable: true, writable: false)]
    public ?string $externId = null;

    #[ApiProperty(readable: true, writable: false)]
    public bool $isSubmittedByCitizen = false;

    #[ApiProperty(readable: true, writable: false)]
    public string $authorName = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $initialOrganisationName = '';
}
