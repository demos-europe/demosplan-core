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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\StatementResourceTypeInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateInternIdException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryStatement;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Doctrine\Common\Collections\ArrayCollection;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use Elastica\Index;
use Webmozart\Assert\Assert;

/**
 * @template-implements ReadableEsResourceTypeInterface<StatementInterface>
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
 * @property-read End $sentAssessmentDate
 */
final class StatementResourceType extends AbstractStatementResourceType implements ReadableEsResourceTypeInterface, StatementResourceTypeInterface
{
    public function __construct(
        FileService $fileService,
        HTMLSanitizer $htmlSanitizer,
        private readonly JsonApiEsService $jsonApiEsService,
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator,
        private readonly QueryStatement $esQuery,
        private readonly StatementService $statementService,
        private readonly StatementDeleter $statementDeleter
    ) {
        parent::__construct($fileService, $htmlSanitizer);
    }

    public function getEntityClass(): string
    {
        return Statement::class;
    }

    public static function getName(): string
    {
        return 'Statement';
    }

    protected function getAccessConditions(): array
    {
        return $this->buildAccessConditions($this);
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
     * @return list<ClauseFunctionInterface<bool>>
     */
    public function buildAccessConditions(StatementResourceType $pathStartResourceType, bool $allowOriginals = false): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
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

        $conditions = [
            // Statement resources can never be deleted
            $this->conditionFactory->propertyHasValue(false, $pathStartResourceType->deleted),
            $this->conditionFactory->propertyIsNull($pathStartResourceType->headStatement->id),
            // statement placeholders are not considered actual statement resources
            $this->conditionFactory->propertyIsNull($pathStartResourceType->movedStatement),
            $this->conditionFactory->propertyHasAnyOfValues(
                $allowedProcedureIds,
                $pathStartResourceType->procedure->id
            ),
        ];
        if (!$allowOriginals) {
            // Normally the path to the relationship would suffice for a NULL check, but the ES
            // provides the 'original.id' path only hence we need the path to the ID to support
            // ES queries beside Doctrine.
            $conditions[] = $this->conditionFactory->propertyIsNotNull($pathStartResourceType->original->id);
        }

        return $conditions;
    }

    /**
     * @throws UserNotFoundException
     */
    public function isAvailable(): bool
    {
        return $this->hasAssessmentPermission()
            || $this->currentUser->hasPermission('area_search_submitter_in_procedures');
    }

    public function isUpdateAllowed(): bool
    {
        if (!$this->hasAssessmentPermission()) {
            return false;
        }

        // has admin list assign permission
        if ($this->currentUser->hasAllPermissions('feature_statement_assignment', 'area_admin_statement_list')) {
            return true;
        }

        // has admin consultation token list permission
        if ($this->currentUser->hasPermission('area_admin_consultations')) {
            return true;
        }

        return false;
    }

    public function getQuery(): AbstractQuery
    {
        return $this->esQuery;
    }

    public function getScopes(): array
    {
        return $this->esQuery->getScopes();
    }

    public function getSearchType(): Index
    {
        return $this->jsonApiEsService->getElasticaTypeForTypeName(self::getName());
    }

    public function getFacetDefinitions(): array
    {
        return [];
    }

    public function deleteEntity(string $entityIdentifier): void
    {
        $this->getTransactionService()->executeAndFlushInTransaction(
            function () use ($entityIdentifier): void {
                $entity = $this->getEntity($entityIdentifier);
                $success = $this->statementDeleter->deleteStatementObject($entity);
                Assert::true($success, "Deletion of statement failed for the given ID '$entityIdentifier'");
            }
        );
    }

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_statement_delete');
    }

    /**
     * some of the following attributes are (currently) only needed in the assessment table,
     * remove them from the defaults when sparse fieldsets are supported.
     *
     * some of the following relationships are (currently) only needed in the assessment table
     */
    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        // some updates are allowed for manual statements only
        $manualStatementCondition = $this->conditionFactory->propertyHasValue(true, $this->manual);
        // currently updates are only needed for "normal" statements
        $simpleStatementCondition = $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyHasValue(false, Paths::statement()->deleted),
            $this->conditionFactory->propertyHasValue(false, Paths::statement()->clusterStatement),
            $this->conditionFactory->propertyIsNull(Paths::statement()->headStatement->id),
            $this->conditionFactory->propertyIsNotNull(Paths::statement()->original->id),
            // all segments must have a segment set, hence the following check is used to ensure this resource type does not return segments
            $this->conditionFactory->isTargetEntityNotInstanceOf(Segment::class)
        );

        /** @var StatementResourceConfigBuilder $configBuilder */
        $configBuilder = parent::getProperties();

        $configBuilder->authorName->aliasedPath(Paths::statement()->meta->authorName);
        $configBuilder->submitName->aliasedPath(Paths::statement()->meta->submitName);
        $configBuilder->authorFeedback->aliasedPath(Paths::statement()->meta->authorFeedback)->readable();
        $configBuilder->similarStatementSubmitters
            ->setRelationshipType($this->getTypes()->getSimilarStatementSubmitterResourceType());

        if ($this->currentUser->hasPermission('area_search_submitter_in_procedures')) {
            $configBuilder->authorName->filterable();
            $configBuilder->submitName->filterable();
        }

        if ($this->currentUser->hasPermission('area_admin_submitters')) {
            $configBuilder->submitName->filterable();
        }

        if ($this->hasAssessmentPermission()) {
            $configBuilder->documentParentId
                ->readable(true, static fn (Statement $statement): ?string => $statement->getDocumentParentId());
            $configBuilder->documentTitle
                ->readable(true, static fn (Statement $statement): ?string => $statement->getDocumentTitle());
            $configBuilder->elementId
                ->readable(true)->aliasedPath(Paths::statement()->element->id);
            $configBuilder->elementTitle
                ->readable(true)->aliasedPath(Paths::statement()->element->title);
            $configBuilder->originalId
                ->readable(true)->aliasedPath(Paths::statement()->original->id);
            $configBuilder->paragraphParentId
                ->readable(true)->aliasedPath(Paths::statement()->paragraph->paragraph->id);
            $configBuilder->paragraphTitle
                ->readable(true)->aliasedPath(Paths::statement()->paragraph->title);
            $configBuilder->assignee->readable()->filterable();
            $configBuilder->authorName->readable(true)->filterable();
            $configBuilder->submitName->readable(true)->filterable()->sortable();
        }

        if ($this->currentUser->hasPermission('area_statement_segmentation')) {
            $configBuilder->segmentDraftList
                ->updatable([$simpleStatementCondition], function (Statement $statement, array $rawJson): array {
                    $encodedJson = Json::encode($rawJson);
                    $statement->setDraftsListJson($encodedJson);

                    return [];
                })
                ->aliasedPath(Paths::statement()->draftsListJson)
                ->readable(false, static function (Statement $statement): ?array {
                    $draftsListJson = $statement->getDraftsListJson();

                    return '' === $draftsListJson ? null : Json::decodeToArray($draftsListJson);
                });
        }

        if ($this->currentUser->hasPermission('feature_similar_statement_submitter')) {
            $configBuilder->similarStatementSubmitters->readable();
        }

        if ($this->currentUser->hasAnyPermissions(
            'feature_segments_of_statement_list',
            'area_statement_segmentation',
            'area_admin_statement_list',
            'area_admin_submitters'
        )) {
            $configBuilder->isSubmittedByCitizen
                ->readable(false, static fn (Statement $statement): bool => $statement->isSubmittedByCitizen());
        }

        // updatable with special permission and on manual statements only
        if ($this->currentUser->hasPermission('area_admin_statement_list')) {
            $configBuilder->fullText
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->text);
            $configBuilder->initialOrganisationName
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaName);
            $configBuilder->initialOrganisationDepartmentName
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaDepartmentName);
            $configBuilder->initialOrganisationPostalCode
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaPostalCode);
            $configBuilder->initialOrganisationCity
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaCity);
            $configBuilder->initialOrganisationHouseNumber
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->houseNumber);
            $configBuilder->initialOrganisationStreet
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaStreet);
            $configBuilder->authorName->updatable([$simpleStatementCondition, $manualStatementCondition]);
            $configBuilder->submitName->updatable([$simpleStatementCondition, $manualStatementCondition]);
            $configBuilder->internId->updatable(
                [$simpleStatementCondition, $manualStatementCondition],
                function (Statement $statement, string $internIdToSet): array {
                    // check for unique
                    $isUnique = $this->statementService->isInternIdUniqueForProcedure($internIdToSet, $statement->getProcedureId());
                    if (!$isUnique) {
                        throw DuplicateInternIdException::create($internIdToSet, $statement->getProcedureId());
                    }

                    $statement->getOriginal()->setInternId($internIdToSet);

                    return [];
                }
            );
            $configBuilder->authoredDate->updatable(
                [$simpleStatementCondition, $manualStatementCondition],
                function (Statement $statement, mixed $newValue): array {
                    $unrequestedChange = false;
                    // authoredDate should be less or equal to the submitDate
                    $submitDate = $statement->getSubmitDateString();
                    if ('' === $newValue || strtotime((string) $submitDate) < strtotime($newValue)) {
                        $newValue = $submitDate;
                        $unrequestedChange = true;
                    }
                    $statement->getMeta()->setAuthoredDate(new DateTime($newValue));

                    return $unrequestedChange ? ['authoredDate'] : [];
                }
            );
            $configBuilder->submitDate->updatable(
                [$simpleStatementCondition, $manualStatementCondition],
                static function (Statement $statement, string $value): array {
                    $statement->setSubmit(new DateTime($value));

                    return [];
                }
            );
            $configBuilder->submitType->updatable(
                [$simpleStatementCondition, $manualStatementCondition],
                static function (Statement $statement, string $submitType): array {
                    $statement->setSubmitType($submitType);

                    return [];
                }
            );
            $configBuilder->submitterEmailAddress->updatable(
                [$simpleStatementCondition, $manualStatementCondition],
                function (Statement $statement, mixed $value): array {
                    $statement->setSubmitterEmailAddress($value);

                    return [];
                }
            );
        }

        // always updatable if access to type and instances was granted
        $configBuilder->assignee
            ->setRelationshipType($this->resourceTypeStore->getClaimResourceType())
            ->updatable([$simpleStatementCondition]);

        if ($this->currentUser->hasPermission('field_statement_memo')) {
            $configBuilder->memo->updatable([$simpleStatementCondition]);
        }

        if ($this->currentUser->hasPermission('area_admin_consultations')) {
            $configBuilder->submitterEmailAddress->updatable([$simpleStatementCondition, $manualStatementCondition]);
            $configBuilder->submitterName
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->submitName);
            $configBuilder->submitterPostalCode
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaPostalCode);
            $configBuilder->submitterCity
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaCity);
            $configBuilder->submitterHouseNumber
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->houseNumber);
            $configBuilder->submitterStreet
                ->updatable([$simpleStatementCondition, $manualStatementCondition])
                ->aliasedPath(Paths::statement()->meta->orgaStreet);
        }

        if ($this->currentUser->hasPermission('feature_similar_statement_submitter')) {
            $configBuilder->similarStatementSubmitters->updatable(
                [$simpleStatementCondition],
                [],
                static function (Statement $statement, array $newValue): array {
                    $statement->setSimilarStatementSubmitters(new ArrayCollection($newValue));

                    return [];
                }
            );
        }

        if ($this->resourceTypeStore->getStatementVoteResourceType()->isAvailable()) {
            $configBuilder->votes
                ->setRelationshipType($this->getTypes()->getStatementVoteResourceType())
                ->readable(true);//this defines if the property is present in the response always
        }


        $configBuilder->organisation->readable()->filterable();
        $configBuilder->sentAssessmentDate->readable();
        $configBuilder->represents->readable();
        $configBuilder->representationCheck->readable();

        $configBuilder->metaMisc
            ->setRelationshipType($this->getTypes()->getStatementMetaMiscResourceType())
            ->aliasedPath(Paths::statement()->meta) //affects readability,updatability,sortable,filterable
            ->readable();

        $configBuilder->original
            ->setRelationshipType($this->getTypes()->getOriginalStatementResourceType())
            ->readable();

        return $configBuilder;
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
