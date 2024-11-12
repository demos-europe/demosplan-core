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

use DemosEurope\DemosplanAddon\Contracts\Entities\SingleDocumentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\StatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<StatementInterface>
 *
 * @property-read ProcedureResourceType $procedure
 * @property-read StatementResourceType $original
 * @property-read End $deleted
 * @property-read End $internId
 * @property-read End $externId
 * @property-read End $authorName
 * @property-read End $fullText
 * @property-read End $initialOrganisationName @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read End $initialOrganisationDepartmentName @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read End $initialOrganisationPostalCode @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read End $initialOrganisationCity @deprecated Should be moved into OrgaSubmitData resource type or something similar
 * @property-read End $initialOrganisationStreet @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read End $initialOrganisationHouseNumber @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read End $initialOrganisationEmail @deprecated Along to other initial statement data, this should be moved into OrgaSubmitData resource type or something similar
 * @property-read End $authoredDate
 * @property-read End $submitDate @deprecated Move into StatementSubmitData resource type or something similar
 * @property-read End $submit
 * @property-read End $submitType used by $submitTypeTranslated, @deprecated Move into StatementSubmitData resource type or something similar
 * @property-read End $submitTypeTranslated @deprecated Only for used by ES to allow finding translated (german) submitType in ES. Will be replaced by a proper and more generic solution.
 * @property-read End $submitName
 * @property-read End $numberOfAnonymVotes
 * @property-read StatementSegmentResourceType $segments
 * @property-read StatementSegmentResourceType $segmentsOfStatement
 * @property-read StatementResourceType $cluster
 * @property-read StatementResourceType $headStatement @deprecated Create a different resource for statements that are part of a cluster
 * @property-read OrgaResourceType $organisation
 * @property-read End $submitterEmailAddress @deprecated Move into StatementSubmitData resource type or something similar
 * @property-read End $submitterName
 * @property-read End $submitterPostalCode
 * @property-read End $submitterCity
 * @property-read End $submitterHouseNumber
 * @property-read End $submitterStreet
 * @property-read End $elementCategory @deprecated Use {@link StatementResourceType::$elements} instead
 * @property-read End $filteredFragmentsCount @deprecated Neither attribute nor relationship. Belongs into API meta response.
 * @property-read End $formerExternId @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read End $fragmentsCount @deprecated Create a {@link StatementFragment} relationship instead
 * @property-read End $isCitizen
 * @property-read End $isCluster @deprecated Cluster statements should get a separate resource type instead, which allows this attribute to be removed
 * @property-read End $clusterStatement
 * @property-read End $likesNum @deprecated Use relationship to {@link StatementLike} instead
 * @property-read End $memo
 * @property-read End $movedFromProcedureId @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read End $movedFromProcedureName @deprecated Use relationship to a PlaceholderStatement resource type instead
 * @property-read End $movedStatementId @deprecated Use {@link StatementResourceType::movedStatement} instead
 * @property-read StatementResourceType $movedStatement @deprecated Should have its own StatementPlaceholderResourceType
 * @property-read End $movedToProcedureId @deprecated See {@link StatementResourceType::$movedStatementId}
 * @property-read End $movedToProcedureName @deprecated See {@link StatementResourceType::$movedStatementId}
 * @property-read End $name @deprecated If still only needed for cluster statements then it should be moved into a separate resource type for such
 * @property-read End $parentId @deprecated Statements in clusters should get a separate resource type where this relationship(!) can be moved into
 * @property-read End $phase
 * @property-read End $polygon
 * @property-read End $priority
 * @property-read End $procedureId @deprecated Use relationship instead
 * @property-read End $publicAllowed @deprecated Use {@link StatementResourceType::$publicVerified} instead
 * @property-read End $publicVerified
 * @property-read End $publicVerifiedTranslation
 * @property-read End $recommendation @deprecated Rename into truncatedRecommendation or something similar
 * @property-read End $recommendationIsTruncated
 * @property-read End $status
 * @property-read End $text @deprecated Rename into truncated_text or something similar
 * @property-read End $textIsTruncated
 * @property-read End $userGroup @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read End $userOrganisation @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read End $userPosition @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read End $userState @deprecated Move into separate resource type (maybe StatementSubmitData resource type or something similar)
 * @property-read End $votePla @deprecated Rename into something understandable
 * @property-read End $votesNum @deprecated Use relationship to {@link StatementVote} instead
 * @property-read End $voteStk @deprecated Rename into something understandable
 * @property-read End $isManual
 * @property-read End $manual
 * @property-read End $anonymous
 * @property-read FileResourceType $files @deprecated Use {@link StatementResourceType::sourceAttachments or @see StatementResourceType::genericAttachments} instead (needs implementation changes)
 * @property-read TagResourceType $tags
 * @property-read PlanningDocumentCategoryResourceType $elements
 * @property-read PlanningDocumentCategoryResourceType $element
 * @property-read CountyResourceType $counties
 * @property-read ParagraphVersionResourceType $paragraph
 * @property-read ParagraphResourceType $paragraphOriginal
 * @property-read PriorityAreaResourceType $priorityAreas
 * @property-read MunicipalityResourceType $municipalities
 * @property-read StatementFragmentsElementsResourceType $fragmentsElements @deprecated Create a {@link StatementFragment} relationship instead
 * @property-read StatementFragmentsElementsResourceType $fragments
 * @property-read SingleDocumentResourceType $document Warning: this does not correspond to {@link Statement::$document} @deprecated Use a relationship to {@link SingleDocumentVersion} instead to get its {@link SingleDocumentVersion::$singleDocument}
 * @property-read StatementMetaResourceType $meta
 * @property-read StatementResourceType $placeholderStatement
 * @property-read StatementResourceType $parent
 *
 * @deprecated please avoid adding properties to this class as it is way too big and convoluted, add properties to the subclasses in which they are needed instead, even if it results in a bit of code duplication
 */
abstract class AbstractStatementResourceType extends DplanResourceType
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly HTMLSanitizer $htmlSanitizer,
        private readonly StatementService $statementService,
    ) {
    }

    /**
     * some of the following attributes are (currently) only needed in the assessment table,
     * remove them from the defaults when sparse fieldsets are supported.
     *
     * some of the following relationships are (currently) only needed in the assessment table
     */
    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        /** @var StatementResourceConfigBuilder $configBuilder */
        $configBuilder = $this->getConfig(StatementResourceConfigBuilder::class);

        $configBuilder->id->readable()->filterable();
        $configBuilder->submitterEmailAddress
            ->readable(true, static fn (Statement $statement): ?string => $statement->getSubmitterEmailAddress());
        $configBuilder->authoredDate
            ->aliasedPath(Paths::statement()->meta->authoredDate)->readable(true, fn (Statement $statement): ?string => $this->formatDate($statement->getMeta()->getAuthoredDateObject()));
        $configBuilder->elementCategory
            ->readable(true)->aliasedPath(Paths::statement()->element->category);
        $configBuilder->externId->readable(true)->filterable()->sortable();
        $configBuilder->filteredFragmentsCount
            ->readable(true, static fn (Statement $statement): int => $statement->getFragmentsFilteredCount());
        $configBuilder->formerExternId
            ->readable(true)->aliasedPath(Paths::statement()->placeholderStatement->externId);
        $configBuilder->fragmentsCount
            ->readable(true, static fn (Statement $statement): int => $statement->getFragments()->count());
        $configBuilder->internId
            ->readable(true)->filterable()->aliasedPath(Paths::statement()->original->internId);
        $configBuilder->isCitizen
            ->readable(true, static fn (Statement $statement): bool => User::ANONYMOUS_USER_ORGA_NAME === $statement->getMeta()->getOrgaName());
        $configBuilder->isCluster
            ->readable(true)->aliasedPath(Paths::statement()->clusterStatement);
        $configBuilder->likesNum
            ->readable(true, static fn (Statement $statement): int => $statement->getLikesNum());
        $configBuilder->movedFromProcedureId
            ->readable(true, static fn (Statement $statement): string => $statement->getMovedFromProcedureId() ?: '');
        $configBuilder->movedFromProcedureName
            ->readable(true, static fn (Statement $statement): string => $statement->getMovedFromProcedureName() ?: '');
        $configBuilder->movedStatementId
            ->readable(true)->aliasedPath(Paths::statement()->movedStatement->id);
        $configBuilder->movedToProcedureId
            ->readable(true, static fn (Statement $statement): string => $statement->getMovedToProcedureId() ?: '');
        $configBuilder->movedToProcedureName
            ->readable(true, static fn (Statement $statement): string => $statement->getMovedToProcedureName() ?: '');
        $configBuilder->name
            ->readable(true, static fn (Statement $statement): string => $statement->getName());
        $configBuilder->initialOrganisationHouseNumber
            ->readable(true)->aliasedPath(Paths::statement()->meta->houseNumber);
        $configBuilder->initialOrganisationStreet
            ->readable(true)->aliasedPath(Paths::statement()->meta->orgaStreet);
        $configBuilder->initialOrganisationCity
            ->readable(true)->aliasedPath(Paths::statement()->meta->orgaCity);
        $configBuilder->initialOrganisationDepartmentName
            ->readable(true)->aliasedPath(Paths::statement()->meta->orgaDepartmentName);
        $configBuilder->initialOrganisationName
            ->readable(true)->sortable()->aliasedPath(Paths::statement()->meta->orgaName);
        $configBuilder->initialOrganisationPostalCode
            ->readable(true)->aliasedPath(Paths::statement()->meta->orgaPostalCode);
        $configBuilder->parentId
            ->readable(true)->aliasedPath(Paths::statement()->parent->id);
        $configBuilder->phase
            ->readable(true,
                fn (Statement $statement): string => $this->statementService->getProcedurePhaseName($statement->getPhase(),
                    $statement->isSubmittedByCitizen())
            );
        $configBuilder->polygon->readable(true);
        $configBuilder->priority->readable(true);
        $configBuilder->procedureId->readable(true)->aliasedPath(Paths::statement()->procedure->id);
        $configBuilder->publicAllowed
            ->readable(true, static fn (Statement $statement): bool => $statement->getPublicAllowed());
        $configBuilder->publicVerified->readable(true);
        $configBuilder->publicVerifiedTranslation
            ->readable(true, static fn (Statement $statement): ?string => $statement->getPublicVerifiedTranslation());
        $configBuilder->recommendation
            ->readable(true, fn (Statement $statement): ?string => $this->htmlSanitizer->purify($statement->getRecommendationShort()));
        $configBuilder->recommendationIsTruncated
            ->readable(true, static fn (Statement $statement): bool => $statement->getRecommendation() !== $statement->getRecommendationShort());
        $configBuilder->status->readable(true);
        $configBuilder->submitDate
            ->sortable()->aliasedPath(Paths::statement()->submit)->readable(true, fn (Statement $statement): ?string => $this->formatDate($statement->getSubmitObject()));
        $configBuilder->submitType->readable(true);
        $configBuilder->text
            ->readable(true, fn (Statement $statement): ?string => $this->htmlSanitizer->purify($statement->getTextShort()));
        $configBuilder->textIsTruncated
            ->readable(true, static fn (Statement $statement): bool => $statement->getText() !== $statement->getTextShort());
        $configBuilder->userGroup
            ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserGroup());
        $configBuilder->userOrganisation
            ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserOrganisation());
        $configBuilder->userPosition
            ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserPosition());
        $configBuilder->userState
            ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserState());
        $configBuilder->votePla->readable(true);
        $configBuilder->votesNum
            ->readable(true, static fn (Statement $statement): int => $statement->getVotesNum());
        $configBuilder->voteStk->readable(true);
        $configBuilder->fullText
            // add the large full text field only if it was requested
            ->readable(false, fn (Statement $statement): string => $this->htmlSanitizer->purify($statement->getText()));
        // keep `isManual` optional, as it may be removed when the resource type is splitted
        $configBuilder->isManual->readable()->aliasedPath(Paths::statement()->manual);
        $configBuilder->numberOfAnonymVotes->filterable();
        $configBuilder->files
            ->setRelationshipType($this->resourceTypeStore->getFileResourceType())
            // files need to be fetched via Filecontainer
            ->readable(false, fn (Statement $statement): array => $this->fileService->getEntityFiles(
                Statement::class,
                $statement->getId(),
                'file')
            );
        $configBuilder->cluster
            ->setRelationshipType($this->resourceTypeStore->getStatementResourceType())
            ->filterable();
        $configBuilder->original
            ->setRelationshipType($this->resourceTypeStore->getStatementResourceType())
            ->filterable();
        $configBuilder->organisation
            ->setRelationshipType($this->resourceTypeStore->getOrgaResourceType())
            ->filterable();
        $configBuilder->headStatement
            ->setRelationshipType($this->resourceTypeStore->getStatementResourceType())
            ->readable()->filterable();
        $configBuilder->segments
            ->setRelationshipType($this->resourceTypeStore->getStatementSegmentResourceType())
            ->readable()->filterable()->aliasedPath(Paths::statement()->segmentsOfStatement);
        $configBuilder->tags
            ->setRelationshipType($this->resourceTypeStore->getTagResourceType())
            ->readable();
        if ($this->currentUser->hasPermission('field_procedure_elements')) {
            $configBuilder->elements
                ->setRelationshipType($this->resourceTypeStore->getPlanningDocumentCategoryResourceType())
                ->readable()->aliasedPath(Paths::statement()->element);
        }
        $configBuilder->counties
            ->setRelationshipType($this->resourceTypeStore->getCountyResourceType())
            ->readable();
        $configBuilder->sourceAttachment
            ->setRelationshipType($this->resourceTypeStore->getSourceStatementAttachmentResourceType())
            ->readable()->setAliasedPath(Paths::statement()->attachments);
        $configBuilder->paragraph
            ->setRelationshipType($this->resourceTypeStore->getParagraphVersionResourceType())
            ->readable();
        $configBuilder->paragraphOriginal
            ->setRelationshipType($this->resourceTypeStore->getParagraphResourceType())
            ->readable()->aliasedPath(Paths::statement()->paragraph->paragraph);
        $configBuilder->priorityAreas
            ->setRelationshipType($this->resourceTypeStore->getPriorityAreaResourceType())
            ->readable();
        $configBuilder->procedure
            ->setRelationshipType($this->resourceTypeStore->getProcedureResourceType())
            ->readable()->filterable();
        $configBuilder->municipalities
            ->setRelationshipType($this->resourceTypeStore->getMunicipalityResourceType())
            ->readable();
        $configBuilder->fragmentsElements
            ->setRelationshipType($this->resourceTypeStore->getStatementFragmentsElementsResourceType())
            ->readable()->aliasedPath(Paths::statement()->fragments);
        $configBuilder->document
            ->setRelationshipType($this->resourceTypeStore->getSingleDocumentResourceType())
            ->readable(false, static function (Statement $statement): ?SingleDocumentInterface {
                $documentVersion = $statement->getDocument();
                if (null === $documentVersion) {
                    return null;
                }

                return $documentVersion->getSingleDocument();
            });

        // this information is needed in FE to show a hint of this statement was given anonymously
        if ($this->currentUser->hasPermission('area_admin_assessmenttable')) {
            $configBuilder->anonymous->readable();
        }

        if ($this->currentUser->hasPermission('area_admin_consultations')) {
            $configBuilder->submitterPostalCode
                ->readable(true)->aliasedPath(Paths::statement()->meta->orgaPostalCode);
            $configBuilder->submitterCity
                ->readable(true)->aliasedPath(Paths::statement()->meta->orgaCity);
            $configBuilder->submitterStreet
                ->readable(true)->aliasedPath(Paths::statement()->meta->orgaStreet);
            $configBuilder->submitterHouseNumber
                ->readable(true)->aliasedPath(Paths::statement()->meta->houseNumber);
            $configBuilder->submitterEmailAddress
                // if the statement was given anonymously, do not display the email-address
                ->readable(true, static fn (Statement $statement): ?string => $statement->isAnonymous() ? null : $statement->getSubmitterEmailAddress());

            $configBuilder->initialOrganisationEmail
                ->readable()->sortable()->aliasedPath(Paths::statement()->meta->orgaEmail);
            $configBuilder->submitterName
                // for citizen statements the submitter name may be empty, hence we use
                // the author name instead for such statements
                ->readable(true, static fn (Statement $statement): ?string => $statement->isCreatedByCitizen()
                    ? $statement->getUserName()
                    : $statement->getMeta()->getSubmitName());
        }

        if ($this->currentUser->hasPermission('field_statement_memo')) {
            $configBuilder->memo->readable(true)->filterable();
        }

        return $configBuilder;
    }
}
