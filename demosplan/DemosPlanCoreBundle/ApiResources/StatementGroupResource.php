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
use demosplan\DemosPlanCoreBundle\StateProcessor\StatementGroupMemberProcessor;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementGroupStateProvider;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ApiResource(
    shortName: 'StatementGroup',
    operations: [
        new Get(uriTemplate: '/StatementGroup/{id}'),
        new GetCollection(uriTemplate: '/StatementGroup'),
        new Post(
            uriTemplate: '/StatementGroup/{id}/members',
            processor: StatementGroupMemberProcessor::class,
            read: false,
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
    public \DateTime $createdDate;

    /** @var string[] */
    #[ApiProperty(readable: true, writable: true)]
    #[SerializedName('memberIds')]
    public array $memberIds = [];

    /** @var string[] */
    #[ApiProperty(readable: false, writable: true)]
    public array $statementIds = [];
}
