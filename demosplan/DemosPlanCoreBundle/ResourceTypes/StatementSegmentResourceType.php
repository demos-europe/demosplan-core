<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\Facets\AssigneesFacet;
use demosplan\DemosPlanCoreBundle\Logic\Facets\PlaceFacet;
use demosplan\DemosPlanCoreBundle\Logic\Facets\TagsFacet;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\StoredQuery\QuerySegment;
use EDT\JsonApi\ResourceTypes\PropertyBuilder;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
use Elastica\Index;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<Segment>
 * @template-implements ReadableEsResourceTypeInterface<Segment>
 *
 * @template-extends DplanResourceType<Segment>
 *
 * @property-read End $recommendation
 * @property-read End $polygon
 * @property-read End $text
 * @property-read End $externId
 * @property-read End $internId
 * @property-read End $orderInProcedure
 * @property-read StatementResourceType $parentStatement
 * @property-read StatementResourceType $parentStatementOfSegment Do not expose! Alias usage only.
 * @property-read AssignableUserResourceType $assignee
 * @property-read TagResourceType $tags
 * @property-read PlaceResourceType $place
 * @property-read SegmentCommentResourceType $comments
 */
final class StatementSegmentResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, ReadableEsResourceTypeInterface
{
    /**
     * @var Index
     */
    private $esType;

    public function __construct(
        private readonly QuerySegment $esQuery,
        JsonApiEsService $jsonApiEsService,
        private readonly PlaceResourceType $placeResourceType,
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator
    ) {
        $this->esType = $jsonApiEsService->getElasticaTypeForTypeName(self::getName());
    }

    public function getEntityClass(): string
    {
        return Segment::class;
    }

    public static function getName(): string
    {
        return 'StatementSegment';
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'feature_json_api_statement_segment',
            // can be included via statements in a view reachable with the following permissions
            'area_admin_statement_list', 'feature_statements_import_excel'
        );
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        $procedureId = $procedure->getId();
        $currentUser = $this->currentUser->getUser();
        $allowedProcedures = $procedure
            ->getSettings()
            ->getAllowedSegmentAccessProcedures()
            ->getValues();
        $procedureIds = $this->procedureAccessEvaluator
            ->filterNonOwnedProcedureIds($currentUser, ...$allowedProcedures);
        $procedureIds[] = $procedureId;

        return $this->conditionFactory->propertyHasAnyOfValues(
            $procedureIds,
            $this->parentStatement->procedure->id
        );
    }

    /**
     * @return array<string,string|null>
     */
    public function getUpdatableProperties(object $updateTarget): array
    {
        $updatableProperties = [
            $this->assignee,
            $this->parentStatement,
            $this->tags,
            $this->text,
            // for now everyone that is allowed to access
            // segments is allowed to change its place
            $this->place,
        ];

        if ($this->currentUser->hasPermission('feature_segment_recommendation_edit')) {
            $updatableProperties[] = $this->recommendation;
        }

        if ($this->currentUser->hasPermission('feature_segment_polygon_set')) {
            $updatableProperties[] = $this->polygon;
        }

        return $this->toProperties(...$updatableProperties);
    }

    /**
     * Prevents updates to the given Segment if it not claimed after the update by the
     * current user.
     *
     * @param Segment             $entity
     * @param array<string,mixed> $properties
     *
     * @return ResourceChange<Segment>
     *
     * @throws UserNotFoundException
     */
    public function updateObject(object $entity, array $properties): ResourceChange
    {
        // update the object first and check its state afterwards
        $resourceChange = new ResourceChange($entity, $this, $properties);

        $parentStatementPropertyPath = $this->parentStatement->getAsNamesInDotNotation();
        $parentStatementOfSegment = null;
        if (\array_key_exists($parentStatementPropertyPath, $properties)) {
            $parentStatementOfSegment = $properties[$parentStatementPropertyPath];
        }

        if (\array_key_exists($parentStatementPropertyPath, $properties)) {
            unset($properties[$parentStatementPropertyPath]);
            $entity->setParentStatementOfSegment($parentStatementOfSegment);
        }

        $this->resourceTypeService->updateObjectNaive($entity, $properties);
        $this->resourceTypeService->validateObject(
            $entity,
            [ResourceTypeService::VALIDATION_GROUP_DEFAULT, Segment::VALIDATION_GROUP_SEGMENT_MANDATORY]
        );

        return $resourceChange;
    }

    public function getFacetDefinitions(): array
    {
        // just in case a user has access to places in different procedures,
        // we add a limitation for the current one only
        $currentProcedure = $this->currentProcedureService->getProcedure();
        $placeCondition = null === $currentProcedure
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasValue(
                $currentProcedure->getId(),
                $this->placeResourceType->procedure->id
            );
        $placeSortMethod = $this->sortMethodFactory->propertyAscending(
            $this->placeResourceType->sortIndex
        );

        return [
            'tags'     => new TagsFacet($this->conditionFactory->false()),
            'assignee' => new AssigneesFacet($this->conditionFactory->false()),
            'place'    => new PlaceFacet($placeCondition, $placeSortMethod),
        ];
    }

    public function getQuery(): AbstractQuery
    {
        return $this->esQuery;
    }

    public function getScopes(): array
    {
        return [AbstractQuery::SCOPE_PLANNER];
    }

    public function getSearchType(): Index
    {
        return $this->esType;
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->recommendation)->readable(true),
            $this->createAttribute($this->text)->readable(true),
            $this->createAttribute($this->externId)->readable(true),
            $this->createAttribute($this->internId)->readable(true),
            $this->createAttribute($this->orderInProcedure)->readable(true),
            $this->createToOneRelationship($this->parentStatement)->readable()->aliasedPath($this->parentStatementOfSegment),
            $this->createToOneRelationship($this->assignee)->readable(),
            $this->createToManyRelationship($this->tags)->readable(),
            // for now all segments have a place, this may change however
            $this->createToOneRelationship($this->place)->readable(),
            $this->createToManyRelationship($this->comments)->readable(),
        ];
        if ($this->currentUser->hasPermission('feature_segment_polygon_read')) {
            $properties[] = $this->createAttribute($this->polygon)->readable(true);
        }

        return array_map(static fn (PropertyBuilder $property): PropertyBuilder => $property->filterable()->sortable(), $properties);
    }
}
