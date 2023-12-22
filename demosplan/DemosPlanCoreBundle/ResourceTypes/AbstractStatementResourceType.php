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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
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
 * @property-read FileResourceType $files @deprecated Use {@link StatementResourceType::$attachments} instead (needs implementation changes)
 * @property-read TagResourceType $tags
 * @property-read PlanningDocumentCategoryResourceType $elements @deprecated Rename to 'element'
 * @property-read PlanningDocumentCategoryResourceType $element
 * @property-read CountyResourceType $counties
 * @property-read StatementAttachmentResourceType $attachments
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
    public function __construct(private readonly FileService $fileService, private readonly HTMLSanitizer $htmlSanitizer)
    {
    }

    /**
     * some of the following attributes are (currently) only needed in the assessment table,
     * remove them from the defaults when sparse fieldsets are supported.
     *
     * some of the following relationships are (currently) only needed in the assessment table
     */
    protected function getProperties(): array
    {
        $submitterEmailAddress = $this->createAttribute($this->submitterEmailAddress)
            ->readable(true, static fn (Statement $statement): ?string => $statement->getSubmitterEmailAddress());
        $properties = [
            $this->createAttribute($this->authoredDate)
                ->aliasedPath($this->meta->authoredDate)->readable(true, fn (Statement $statement): ?string => $this->formatDate($statement->getMeta()->getAuthoredDateObject()), true),
            $this->createAttribute($this->elementCategory)
                ->readable(true)->aliasedPath($this->element->category),
            $this->createAttribute($this->externId)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->filteredFragmentsCount)
                ->readable(true, static fn (Statement $statement): int => $statement->getFragmentsFilteredCount()),
            $this->createAttribute($this->formerExternId)
                ->readable(true)->aliasedPath($this->placeholderStatement->externId),
            $this->createAttribute($this->fragmentsCount)
                ->readable(true, static fn (Statement $statement): int => $statement->getFragments()->count()),
            $this->createAttribute($this->id)->readable(true)->filterable(),
            $this->createAttribute($this->internId)
                ->readable(true)->filterable()->aliasedPath($this->original->internId),
            $this->createAttribute($this->isCitizen)
                ->readable(true, static fn (Statement $statement): bool => User::ANONYMOUS_USER_ORGA_NAME === $statement->getMeta()->getOrgaName()),
            $this->createAttribute($this->isCluster)
                ->readable(true)->aliasedPath($this->clusterStatement),
            $this->createAttribute($this->likesNum)
                ->readable(true, static fn (Statement $statement): int => $statement->getLikesNum()),
            $this->createAttribute($this->movedFromProcedureId)
                ->readable(true, static fn (Statement $statement): string => $statement->getMovedFromProcedureId() ?: ''),
            $this->createAttribute($this->movedFromProcedureName)
                ->readable(true, static fn (Statement $statement): string => $statement->getMovedFromProcedureName() ?: ''),
            $this->createAttribute($this->movedStatementId)
                ->readable(true)->aliasedPath($this->movedStatement->id),
            $this->createAttribute($this->movedToProcedureId)
                ->readable(true, static fn (Statement $statement): string => $statement->getMovedToProcedureId() ?: ''),
            $this->createAttribute($this->movedToProcedureName)
                ->readable(true, static fn (Statement $statement): string => $statement->getMovedToProcedureName() ?: ''),
            $this->createAttribute($this->name)
                ->readable(true, static fn (Statement $statement): string => $statement->getName()),
            $this->createAttribute($this->initialOrganisationHouseNumber)
                ->readable(true)->aliasedPath($this->meta->houseNumber),
            $this->createAttribute($this->initialOrganisationStreet)
                ->readable(true)->aliasedPath($this->meta->orgaStreet),
            $this->createAttribute($this->initialOrganisationCity)
                ->readable(true)->aliasedPath($this->meta->orgaCity),
            $this->createAttribute($this->initialOrganisationDepartmentName)
                ->readable(true)->aliasedPath($this->meta->orgaDepartmentName),
            $this->createAttribute($this->initialOrganisationName)
                ->readable(true)->sortable()->aliasedPath($this->meta->orgaName),
            $this->createAttribute($this->initialOrganisationPostalCode)
                ->readable(true)->aliasedPath($this->meta->orgaPostalCode),
            $this->createAttribute($this->parentId)
                ->readable(true)->aliasedPath($this->parent->id),
            $this->createAttribute($this->phase)
                ->readable(true, function (Statement $statement): string {
                    $phase = $statement->getPhase();
                    if (Statement::INTERNAL === $statement->getPublicStatement()) {
                        $internalPhases = $this->globalConfig->getInternalPhasesAssoc();
                        $phase = $internalPhases[$phase]['name'] ?? '';
                    } else {
                        $externalPhases = $this->globalConfig->getExternalPhasesAssoc();
                        $phase = $externalPhases[$phase]['name'] ?? '';
                    }

                    return $phase;
                }),
            $this->createAttribute($this->polygon)->readable(true),
            $this->createAttribute($this->priority)->readable(true),
            $this->createAttribute($this->procedureId)->readable(true)->aliasedPath($this->procedure->id),
            $this->createAttribute($this->publicAllowed)
                ->readable(true, static fn (Statement $statement): bool => $statement->getPublicAllowed()),
            $this->createAttribute($this->publicVerified)->readable(true),
            $this->createAttribute($this->publicVerifiedTranslation)
                ->readable(true, static fn (Statement $statement): ?string => $statement->getPublicVerifiedTranslation()),
            $this->createAttribute($this->recommendation)
                ->readable(true, fn (Statement $statement): ?string => $this->htmlSanitizer->purify($statement->getRecommendationShort())),
            $this->createAttribute($this->recommendationIsTruncated)
                ->readable(true, static fn (Statement $statement): bool => $statement->getRecommendation() !== $statement->getRecommendationShort()),
            $this->createAttribute($this->status)->readable(true),
            $this->createAttribute($this->submitDate)
                ->sortable()->aliasedPath($this->submit)->readable(true, fn (Statement $statement): ?string => $this->formatDate($statement->getSubmitObject()), true),
            $submitterEmailAddress,
            $this->createAttribute($this->submitType)->readable(true),
            $this->createAttribute($this->text)
                ->readable(true, fn (Statement $statement): ?string => $this->htmlSanitizer->purify($statement->getTextShort())),
            $this->createAttribute($this->textIsTruncated)
                ->readable(true, static fn (Statement $statement): bool => $statement->getText() !== $statement->getTextShort()),
            $this->createAttribute($this->userGroup)
                ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserGroup()),
            $this->createAttribute($this->userOrganisation)
                ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserOrganisation()),
            $this->createAttribute($this->userPosition)
                ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserPosition()),
            $this->createAttribute($this->userState)
                ->readable(true, static fn (Statement $statement): ?string => $statement->getMeta()->getUserState()),
            $this->createAttribute($this->votePla)->readable(true),
            $this->createAttribute($this->votesNum)
                ->readable(true, static fn (Statement $statement): int => $statement->getVotesNum()),
            $this->createAttribute($this->voteStk)->readable(true),
            $this->createAttribute($this->fullText)
                // add the large full text field only if it was requested
                ->readable(false, fn (Statement $statement): string => $this->htmlSanitizer->purify($statement->getText())),
            // keep `isManual` optional, as it may be removed when the resource type is splitted
            $this->createAttribute($this->isManual)->readable()->aliasedPath($this->manual),
            $this->createAttribute($this->numberOfAnonymVotes)->filterable(),
            $this->createToManyRelationship($this->files)
                ->readable(false, function (Statement $statement): ?array {
                    // files need to be fetched via Filecontainer
                    $files = $this->fileService->getEntityFiles(Statement::class, $statement->getId(), 'file');
                    if (0 === count($files)) {
                        return null;
                    }

                    return $files;
                }),
            $this->createToManyRelationship($this->cluster)->filterable(),
            $this->createToOneRelationship($this->original)->filterable(),
            $this->createToOneRelationship($this->organisation)->filterable(),
            $this->createToOneRelationship($this->headStatement)->readable()->filterable(),
            $this->createToManyRelationship($this->segments)->readable()->filterable()->aliasedPath($this->segmentsOfStatement),
            $this->createToManyRelationship($this->tags)->readable(),
            $this->createToManyRelationship($this->elements)->readable()->aliasedPath($this->element),
            $this->createToManyRelationship($this->counties)->readable(),
            $this->createToManyRelationship($this->attachments)->readable(),
            $this->createToOneRelationship($this->paragraph)->readable(),
            $this->createToOneRelationship($this->paragraphOriginal)->readable()->aliasedPath($this->paragraph->paragraph),
            $this->createToManyRelationship($this->priorityAreas)->readable(),
            $this->createToOneRelationship($this->procedure)->readable()->filterable(),
            $this->createToManyRelationship($this->municipalities)->readable(),
            $this->createToManyRelationship($this->fragmentsElements)->readable()->aliasedPath($this->fragments),
            $this->createToOneRelationship($this->document)
                ->readable(false, static function (Statement $statement): ?SingleDocument {
                    $documentVersion = $statement->getDocument();
                    if (null === $documentVersion) {
                        return null;
                    }

                    return $documentVersion->getSingleDocument();
                }),
        ];

        // this information is needed in FE to show a hint of this statement was given anonymously
        if ($this->currentUser->hasPermission('area_admin_assessmenttable')) {
            $properties[] = $this->createAttribute($this->anonymous)->readable();
        }

        if ($this->currentUser->hasPermission('area_admin_consultations')) {
            $properties[] = $this->createAttribute($this->submitterPostalCode)
                ->readable(true)->aliasedPath($this->meta->orgaPostalCode);
            $properties[] = $this->createAttribute($this->submitterCity)
                ->readable(true)->aliasedPath($this->meta->orgaCity);
            $properties[] = $this->createAttribute($this->submitterStreet)
                ->readable(true)->aliasedPath($this->meta->orgaStreet);
            $properties[] = $this->createAttribute($this->submitterHouseNumber)
                ->readable(true)->aliasedPath($this->meta->houseNumber);
            $submitterEmailAddress
                // if the statement was given anonymously, do not display the email-address
                ->readable(true, static fn (Statement $statement): ?string => $statement->isAnonymous() ? null : $statement->getSubmitterEmailAddress());

            $properties[] = $this->createAttribute($this->initialOrganisationEmail)
                ->readable()->sortable()->aliasedPath($this->meta->orgaEmail);
            $properties[] = $this->createAttribute($this->submitterName)
                // for citizen statements the submitter name may be empty, hence we use
                // the author name instead for such statements
                ->readable(true, static fn (Statement $statement): ?string => $statement->isCreatedByCitizen()
                    ? $statement->getUserName()
                    : $statement->getMeta()->getSubmitName());
        }

        if ($this->currentUser->hasPermission('field_statement_memo')) {
            $properties[] = $this->createAttribute($this->memo)->readable(true)->filterable();
        }

        return $properties;
    }
}
