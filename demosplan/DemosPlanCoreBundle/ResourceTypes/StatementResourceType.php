<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementResourceTypeService;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryStatement;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PathsBasedInterface;
use Elastica\Type;

/**
 * @template-implements ReadableEsResourceTypeInterface<Statement>
 * @template-implements UpdatableDqlResourceTypeInterface<Statement>
 * @template-implements DeletableDqlResourceTypeInterface<Statement>
 *
 * @property-read ClaimResourceType $assignee
 * @property-read End $documentParentId @deprecated Use {@link StatementResourceType::$document} instead
 * @property-read End $documentTitle @deprecated Use a relationship to {@link SingleDocumentVersion} instead
 * @property-read End $draftsListJson
 * @property-read End $elementId @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read End $elementTitle @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read End $isSubmittedByCitizen
 * @property-read End $originalId @deprecated Use a relationship instead
 * @property-read End $paragraphParentId @deprecated Use {@link StatementResourceType::$paragraph} instead
 * @property-read End $paragraphTitle @deprecated Use {@link StatementResourceType::$paragraph} instead
 * @property-read End $segmentDraftList
 * @property-read SimilarStatementSubmitterResourceType $similarStatementSubmitters
 */
final class StatementResourceType extends AbstractStatementResourceType implements ReadableEsResourceTypeInterface, UpdatableDqlResourceTypeInterface, DeletableDqlResourceTypeInterface
{
    /**
     * @var QueryStatement
     */
    private $esQuery;

    /**
     * @var StatementResourceTypeService
     */
    private $statementResourceTypeService;

    /**
     * @var JsonApiEsService
     */
    private $jsonApiEsService;

    /**
     * @var ProcedureAccessEvaluator
     */
    private $procedureAccessEvaluator;

    public function __construct(
        FileService $fileService,
        HTMLSanitizer $htmlSanitizer,
        JsonApiEsService $jsonApiEsService,
        ProcedureAccessEvaluator $procedureAccessEvaluator,
        QueryStatement $queryStatement,
        StatementResourceTypeService $statementResourceTypeService
    ) {
        parent::__construct($fileService, $htmlSanitizer);
        $this->esQuery = $queryStatement;
        $this->statementResourceTypeService = $statementResourceTypeService;
        $this->jsonApiEsService = $jsonApiEsService;
        $this->procedureAccessEvaluator = $procedureAccessEvaluator;
    }

    public function getEntityClass(): string
    {
        return Statement::class;
    }

    public static function getName(): string
    {
        return 'Statement';
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->buildAccessCondition($this);
    }

    /**
     * This method builds the access condition for this resource type.
     *
     * During the build the method will use paths to properties of the statement. Usually it would
     * be sufficient to start at the `Statement` resource type, but this limits the created
     * condition to be used to fetch statements only. By allowing the starting point of the path
     * to be defined via `$pathStartResourceType` it is possible to apply the created condition to
     * a relationship.
     *
     * A (currently only) example for such usage is the {@link StatementAttachmentResourceType}.
     * An attachment should only be accessible if the corresponding statement is accessible. Hence,
     * to make the returned condition usable when fetching attachments, all paths needs to be
     * prefixed with `statement`, as this is the name of the relationship from the
     * {@link StatementAttachmentResourceType} to the {@link StatementResourceType}.
     *
     * @return FunctionInterface<bool>
     */
    public function buildAccessCondition(StatementResourceType $pathStartResourceType): FunctionInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        $configuredProcedures = $procedure
            ->getSettings()
            ->getAllowedSegmentAccessProcedures()
            ->getValues();

        $currentUser = $this->currentUser->getUser();
        $allowedProcedureIds = $this->procedureAccessEvaluator->filterNonOwnedProcedureIds(
            $currentUser,
            ...$configuredProcedures
        );
        $allowedProcedureIds[] = $procedure->getId();

        return $this->conditionFactory->allConditionsApply(
        // Statement resources can never be deleted
            $this->conditionFactory->propertyHasValue(false, ...$pathStartResourceType->deleted),
            // Normally the path to the relationship would suffice for a NULL check, but the ES
            // provides the 'original.id' path only hence we need the path to the ID to support
            // ES queries beside Doctrine.
            $this->conditionFactory->propertyIsNotNull(...$pathStartResourceType->original->id),
            $this->conditionFactory->propertyIsNull(...$pathStartResourceType->headStatement->id),
            // statement placeholders are not considered actual statement resources
            $this->conditionFactory->propertyIsNull(...$pathStartResourceType->movedStatement),
            $this->conditionFactory->propertyHasAnyOfValues(
                $allowedProcedureIds,
                ...$pathStartResourceType->procedure->id
            )
        );
    }

    public function updateObject(object $object, array $properties): ResourceChange
    {
        // currently updates are only needed for normal statements
        $object = $this->getAsSimpleStatement($object);

        return $this->statementResourceTypeService->update($object, $this, $properties);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UserNotFoundException
     */
    public function isAvailable(): bool
    {
        return $this->hasAssessmentPermission()
            || $this->currentUser->hasAnyPermissions(
                'area_search_submitter_in_procedures',
            );
    }

    public function getQuery(): AbstractQuery
    {
        return $this->esQuery;
    }

    public function getScopes(): array
    {
        return $this->esQuery->getScopes();
    }

    public function getSearchType(): Type
    {
        return $this->jsonApiEsService->getElasticaTypeForTypeName(self::getName());
    }

    public function getFacetDefinitions(): array
    {
        return [];
    }

    /**
     * Ensures the given $object is a normal statement; ie:
     * * not a segment
     * * not an original statement
     * * not a cluster
     * * not part of a cluster
     * * not deleted.
     *
     * @return Statement The given $object
     */
    private function getAsSimpleStatement(object $object): Statement
    {
        if (!$object instanceof Statement
            || $object->isSegment()
            || $object->isOriginal()
            || $object->isDeleted()
            || $object->isClusterStatement()
            || $object->isInCluster()) {
            throw new InvalidArgumentException('Invalid target object');
        }

        return $object;
    }

    /**
     * @param Statement $entity
     */
    public function delete(object $entity): ResourceChange
    {
        if (!$this->currentUser->hasPermission('feature_statement_delete')) {
            throw new InvalidArgumentException('Insufficient permissions');
        }

        $success = $this->statementResourceTypeService->deleteStatement($entity);
        if (true !== $success) {
            throw new InvalidArgumentException('Deletion request could not be executed.');
        }
        // TODO: refactor deleteStatement to return ResourceChange to not break transactions and improve performance
        $resourceChange = new ResourceChange($entity, $this, []);
        $resourceChange->addEntityToDelete($entity);

        return $resourceChange;
    }

    /**
     * @param Statement $object
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws UserNotFoundException
     */
    public function getUpdatableProperties(object $object): array
    {
        // has admin list assign permission
        $adminListAssignPermission = $this->currentUser->hasAllPermissions('feature_statement_assignment', 'area_admin_statement_list');
        // has admin consultation token list permission
        $adminConsultationTokenListPermission = $this->currentUser->hasPermission('area_admin_consultations');

        if (!$adminListAssignPermission && !$adminConsultationTokenListPermission) {
            return [];
        }

        // updatable with special permission and an manual statements only
        if ($this->currentUser->hasPermission('area_admin_statement_list') && $object->isManual()) {
            $writableProperties = [
                $this->fullText,
                $this->initialOrganisationName,
                $this->initialOrganisationDepartmentName,
                $this->initialOrganisationPostalCode,
                $this->initialOrganisationCity,
                $this->initialOrganisationHouseNumber,
                $this->initialOrganisationStreet,
                $this->authorName,
                $this->submitName,
                $this->internId,
                $this->authoredDate,
                $this->submitDate,
                $this->submitType,
                $this->submitterEmailAddress,
            ];
        } else {
            $writableProperties = [];
        }

        // always updatable if access to type and instances was granted
        $writableProperties[] = $this->assignee;

        if ($this->currentUser->hasPermission('field_statement_memo')) {
            $writableProperties[] = $this->memo;
        }

        if ($object->isManual() && $this->currentUser->hasPermission('area_admin_consultations')) {
            $writableProperties = array_merge($writableProperties, [
                $this->submitterEmailAddress,
                $this->submitterName,
                $this->submitterPostalCode,
                $this->submitterCity,
                $this->submitterHouseNumber,
                $this->submitterStreet,
            ]);
        }

        if ($this->currentUser->hasPermission('area_statement_segmentation')) {
            $writableProperties[] = $this->segmentDraftList;
        }

        if ($this->currentUser->hasPermission('feature_similar_statement_submitter')) {
            $writableProperties[] = $this->similarStatementSubmitters;
        }

        // the isManual() returns true if a planner createed the statement
        // It is not intended to overwrite a polygon given by a private-person/non planner
        if ($this->currentUser->hasPermission('feature_statement_polygon_set')
            && $object->isManual()
        ) {
            $writableProperties[] = $this->polygon;
        }

        return $this->toProperties(...$writableProperties);
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    /**
     * some of the following attributes are (currently) only needed in the assessment table,
     * remove them from the defaults when sparse fieldsets are supported.
     *
     * some of the following relationships are (currently) only needed in the assessment table
     */
    protected function getProperties(): array
    {
        $properties = parent::getProperties();

        $authorName = $this->createAttribute($this->authorName)->aliasedPath($this->meta->authorName);
        $submitName = $this->createAttribute($this->submitName)->aliasedPath($this->meta->submitName);
        $properties[] = $authorName;
        $properties[] = $submitName;

        if ($this->currentUser->hasPermission('area_search_submitter_in_procedures')) {
            $authorName->filterable();
            $submitName->filterable();
        }

        if ($this->currentUser->hasPermission('area_admin_submitters')) {
            $submitName->filterable();
        }

        if ($this->hasAssessmentPermission()) {
            $properties[] = $this->createAttribute($this->documentParentId)
                ->readable(true, static function (Statement $statement): ?string {
                    return $statement->getDocumentParentId();
                });
            $properties[] = $this->createAttribute($this->documentTitle)
                ->readable(true, static function (Statement $statement): ?string {
                    return $statement->getDocumentTitle();
                });
            $properties[] = $this->createAttribute($this->elementId)
                ->readable(true)->aliasedPath($this->element->id);
            $properties[] = $this->createAttribute($this->elementTitle)
                ->readable(true)->aliasedPath($this->element->title);
            $properties[] = $this->createAttribute($this->originalId)
                ->readable(true)->aliasedPath($this->original->id);
            $properties[] = $this->createAttribute($this->paragraphParentId)
                ->readable(true)->aliasedPath($this->paragraph->paragraph->id);
            $properties[] = $this->createAttribute($this->paragraphTitle)
                ->readable(true)->aliasedPath($this->paragraph->title);
            $properties[] = $this->createToOneRelationship($this->assignee)->readable()->filterable();
            $authorName->readable(true)->filterable();
            $submitName->readable(true)->filterable()->sortable();
        }

        if ($this->currentUser->hasPermission('area_statement_segmentation')) {
            $properties[] = $this->createAttribute($this->segmentDraftList)
                ->readable(false, static function (Statement $statement): ?array {
                    $draftsListJson = $statement->getDraftsListJson();

                    return '' === $draftsListJson ? null : Json::decodeToArray($draftsListJson);
                });
        }

        if ($this->currentUser->hasPermission('feature_similar_statement_submitter')) {
            $properties[] = $this->createToManyRelationship($this->similarStatementSubmitters)->readable();
        }

        if ($this->currentUser->hasAnyPermissions(
            'feature_segments_of_statement_list',
            'area_statement_segmentation',
            'area_admin_statement_list',
            'area_admin_submitters'
        )) {
            $properties[] = $this->createAttribute($this->isSubmittedByCitizen)
                ->readable(false, static function (Statement $statement): bool {
                    return $statement->isSubmittedByCitizen();
                });
        }

        return $properties;
    }

    /**
     * Returns `true` if the current user has the permission to use properties to assess the statement.
     * If it returns `false` the resource type may still be usable, but with a very
     * limited set of properties only.
     */
    private function hasAssessmentPermission(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            'area_admin_assessmenttable',
            'feature_json_api_statement',
            // allow access for the consultation token admin list
            'area_admin_consultations'
        );
    }
}
