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
use ApiPlatform\Metadata\Post;
use demosplan\DemosPlanCoreBundle\StateProcessor\StatementGroupCreateProcessor;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/statements/{procedureId}/statements/group',
            processor: StatementGroupCreateProcessor::class,
            read: false,
            deserialize: false,
        ),
    ],
    formats: ['jsonapi'],
    routePrefix: '/1.0',
)]
class StatementGroupCreateResource
{
    #[ApiProperty(identifier: true)]
    public string $id = '';
}
