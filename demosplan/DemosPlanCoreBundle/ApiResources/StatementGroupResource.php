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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use DateTime;
use demosplan\DemosPlanCoreBundle\StateProcessor\StatementGroupProcessor;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementGroupStateProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    shortName: 'StatementGroup',
    operations: [
        new Get(uriTemplate: '/StatementGroup'),
        new GetCollection(uriTemplate: '/StatementGroup'),
        new Post(
            uriTemplate: '/StatementGroup',
            read: false,
            processor: StatementGroupProcessor::class,
        ),
    ],
    formats: ['jsonapi'],
    routePrefix: '/3.0',
    provider: StatementGroupStateProvider::class,
)]
#[ApiFilter(PropertyFilter::class)]
class StatementGroupResource
{
    #[ApiProperty(readable: false, identifier: true)]
    public string $id;

    #[SerializedName('createdDate')]
    public DateTime $createdDate;

    #[ApiProperty(readable: true, writable: true)]
    public ?string $groupName = null;

    #[ApiProperty(readable: true, writable: true)]
    public ?string $headStatementId = null;

    /** @var StatementResource[] */
    #[ApiProperty(readable: true, writable: true)]
    public array $statements = [];
}
