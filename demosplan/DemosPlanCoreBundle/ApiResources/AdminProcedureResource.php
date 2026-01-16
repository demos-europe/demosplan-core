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
use demosplan\DemosPlanCoreBundle\StateProcessor\AdminProcedureStateProcesor;
use demosplan\DemosPlanCoreBundle\StateProvider\AdminProcedureStateProvider;

#[ApiResource(
    shortName: 'AdminProcedure',
    operations: [
        new Get(),
        new GetCollection(),
        new Patch(),
    ],
    formats: ['jsonapi'],
    routePrefix: '/3.0',
    provider: AdminProcedureStateProvider::class,
    processor: AdminProcedureStateProcesor::class,
)]
class AdminProcedureResource
{
    public string $id;

    public string $name;

    public string $externalName;
}
