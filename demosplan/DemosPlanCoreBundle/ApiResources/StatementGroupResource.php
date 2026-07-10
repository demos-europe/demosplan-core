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
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\StateProcessor\StatementGroupProcessor;
use demosplan\DemosPlanCoreBundle\StateProcessor\StatementGroupRelationshipProcessor;
use demosplan\DemosPlanCoreBundle\StateProvider\StatementGroupStateProvider;

#[ApiResource(
    shortName: 'StatementGroup',
    operations: [
        new GetCollection(uriTemplate: '/StatementGroup'),
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
        new Delete(
            uriTemplate: '/StatementGroup/{id}',
            read: false,
            deserialize: false,
            output: false,
            processor: StatementGroupProcessor::class,
        ),
        new Post(
            uriTemplate: '/StatementGroup/{id}/relationships/statements',
            read: false,
            deserialize: false,
            processor: StatementGroupRelationshipProcessor::class,
        ),
        new Delete(
            uriTemplate: '/StatementGroup/{id}/relationships/statements',
            read: false,
            deserialize: false,
            output: false,
            processor: StatementGroupRelationshipProcessor::class,
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

    #[ApiProperty(readable: true, writable: false)]
    public ?string $externId = null;

    #[ApiProperty(readable: true, writable: false)]
    public bool $isSubmittedByCitizen = false;

    #[ApiProperty(readable: true, writable: false)]
    public string $authorName = '';

    #[ApiProperty(readable: true, writable: false)]
    public string $initialOrganisationName = '';

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
        $resource->externId = $statement->getExternId();
        $resource->isSubmittedByCitizen = $statement->isSubmittedByCitizen();
        // Submission-time snapshots, not the live org; return '' not null.
        $resource->authorName = $statement->getMeta()->getAuthorName();
        $resource->initialOrganisationName = $statement->getMeta()->getOrgaName();
        $resource->statements = array_map(
            static function (Statement $member): StatementResource {
                $statementResource = new StatementResource();
                $statementResource->id = $member->getId();
                $statementResource->externId = $member->getExternId();
                $statementResource->isSubmittedByCitizen = $member->isSubmittedByCitizen();
                // Submission-time snapshots, not the live org; return '' not null.
                $statementResource->authorName = $member->getMeta()->getAuthorName();
                $statementResource->initialOrganisationName = $member->getMeta()->getOrgaName();

                return $statementResource;
            },
            $statement->getCluster()->toArray()
        );
        $resource->statementsCount = count($resource->statements);

        return $resource;
    }
}
