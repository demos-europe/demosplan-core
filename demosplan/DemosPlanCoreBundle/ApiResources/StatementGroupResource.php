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
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\StateProcessor\StatementGroupProcessor;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementGroupStateProvider;

#[ApiResource(
    shortName: 'StatementGroup',
    operations: [
        new Get(uriTemplate: '/StatementGroup/{id}'),
        new Post(
            uriTemplate: '/StatementGroup',
            read: false,
            processor: StatementGroupProcessor::class,
        ),
        new Patch(
            uriTemplate: '/StatementGroup/{id}',
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

    #[ApiProperty(readable: true, writable: true)]
    public ?string $groupName = null;

    #[ApiProperty(readable: false, writable: true)]
    public ?string $headStatementId = null;

    /** @var StatementResource[] */
    #[ApiProperty(readable: true, writable: true)]
    public array $statements = [];

    #[ApiProperty(readable: true, writable: false)]
    public int $statementsCount = 0;

    /**
     * Builds the resource from a cluster (head) statement, used by both the
     * read provider and the create processor so their output is identical.
     */
    public static function fromStatement(Statement $statement): self
    {
        $resource = new self();
        $resource->id = $statement->getId();
        $resource->groupName = $statement->getName();
        $resource->statements = array_map(
            static function (Statement $member): StatementResource {
                $statementResource = new StatementResource();
                $statementResource->id = $member->getId();

                return $statementResource;
            },
            $statement->getCluster()->toArray()
        );
        $resource->statementsCount = count($resource->statements);

        return $resource;
    }
}
