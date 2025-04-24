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

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
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
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\PropertyConfig\Builder\PropertyConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;
use Elastica\Index;

/**
 * @template-implements ReadableEsResourceTypeInterface<SegmentInterface>
 *
 * @template-extends DplanResourceType<SegmentInterface>
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
 * @property-read End $customFields
 */
final class StatementSegmentResourceType extends DplanResourceType implements ReadableEsResourceTypeInterface
{
    /**
     * @var Index
     */
    private $esType;

    public function __construct(
        private readonly QuerySegment $esQuery,
        JsonApiEsService $jsonApiEsService,
        private readonly PlaceResourceType $placeResourceType,
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator,
        private readonly CustomFieldValueCreator $customFieldValueCreator,
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

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_json_api_statement_segment');
    }

    /**
     * @throws PathException
     */
    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
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

        return [] === $procedureIds
            ? [$this->conditionFactory->false()]
            : [$this->conditionFactory->propertyHasAnyOfValues($procedureIds, $this->parentStatementOfSegment->procedure->id)];
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

    protected function getProperties(): array
    {
        $recommendation = $this->createAttribute($this->recommendation)->readable(true);
        $polygon = $this->createAttribute($this->polygon);

        $properties = [
            $this->createIdentifier()->readable(),
            $recommendation,
            $polygon,
            $this->createAttribute($this->text)->readable(true)->updatable(),
            $this->createAttribute($this->externId)->readable(true),
            $this->createAttribute($this->internId)->readable(true),
            $this->createAttribute($this->orderInProcedure)->readable(true),
            $this->createToOneRelationship($this->parentStatement)
                ->setRelationshipType($this->resourceTypeStore->getStatementResourceType())
                ->readable()->updatable()->aliasedPath($this->parentStatementOfSegment),
            $this->createToOneRelationship($this->assignee)->readable()->updatable(),
            $this->createToManyRelationship($this->tags)->readable()->updatable(),
            // for now all segments have a place, this may change however
            $this->createToOneRelationship($this->place)->readable()
                // for now everyone that is allowed to access
                // segments is allowed to change its place
                ->updatable(),
        ];

        if ($this->currentUser->hasPermission('feature_segment_comment_list_on_segment')) {
            $properties[] = $this->createToManyRelationship($this->comments)->readable();
        }
        if ($this->currentUser->hasPermission('feature_segment_polygon_read')) {
            $polygon->readable(true);
        }
        if ($this->currentUser->hasPermission('feature_segment_polygon_set')) {
            $polygon->updatable();
        }
        if ($this->currentUser->hasPermission('feature_segment_recommendation_edit')) {
            $recommendation->updatable();
        }

        if ($this->currentUser->hasPermission('area_admin_custom_fields')) {
            $properties[] = $this->createAttribute($this->customFields)
                ->setReadableByCallable(static fn (Segment $segment): ?array => $segment->getCustomFields()?->toJson())
                ->addUpdateBehavior(new CallbackAttributeSetBehaviorFactory([], function (Segment $segment, array $customFields): array {
                    $customFieldList = $segment->getCustomFields() ?? new CustomFieldValuesList();
                    $customFieldList = $this->customFieldValueCreator->updateOrAddCustomFieldValues($customFieldList, $customFields, $segment->getProcedure()->getId(), 'PROCEDURE', 'SEGMENT');
                    $segment->setCustomFields($customFieldList->toJson());

                    return [];
                }, OptionalField::YES)
                );
        }

        return array_map(
            static fn (PropertyConfigBuilderInterface $property): PropertyConfigBuilderInterface => $property
                ->filterable()
                ->sortable(),
            $properties
        );
    }

    public function getUpdateValidationGroups(): array
    {
        return [ResourceTypeService::VALIDATION_GROUP_DEFAULT, SegmentInterface::VALIDATION_GROUP_SEGMENT_MANDATORY];
    }
}
