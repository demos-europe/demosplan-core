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

use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<StatementFragment>
 *
 * @property-read End $displayId
 * @property-read End $text
 * @property-read End $created
 * @property-read End $modified
 * @property-read End $assignedToFbDate
 * @property-read End $archivedOrgaName
 * @property-read End $archivedVoteUserName
 * @property-read End $status
 * @property-read End $elementTitle
 * @property-read End $elementId
 * @property-read End $paragraphTitle
 * @property-read End $paragraphId
 * @property-read End $paragraphParentTitle
 * @property-read End $paragraphParentId
 * @property-read End $documentParentTitle
 * @property-read End $documentParentId
 * @property-read End $consideration
 * @property-read End $considerationAdvice
 * @property-read End $vote
 * @property-read End $voteAdvice
 * @property-read End $deleted
 * @property-read StatementResourceType $statement
 * @property-read DepartmentResourceType $department
 * @property-read TagResourceType $tags
 * @property-read CountyResourceType $counties
 * @property-read MunicipalityResourceType $municipalities
 * @property-read PriorityAreaResourceType $priorityAreas
 * @property-read PlanningDocumentCategoryResourceType $element
 * @property-read ClaimResourceType $assignee
 * @property-read ClaimResourceType $lastClaimed
 * @property-read ClaimResourceType $lastClaimedUser
 * @property-read ParagraphVersionResourceType $paragraph
 * @property-read ProcedureResourceType $procedure
 */
final class StatementFragmentResourceType extends DplanResourceType
{
    public function __construct(private readonly HTMLSanitizer $htmlSanitizer)
    {
    }

    public static function getName(): string
    {
        return 'StatementFragment';
    }

    protected function getProperties(): array
    {
        $properties = [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->displayId)->readable(true, static fn (StatementFragment $fragment): string => $fragment->getDisplayId()),
            $this->createAttribute($this->text)->readable(true, fn (StatementFragment $fragment): string => $this->htmlSanitizer->purify($fragment->getText())),
            $this->createAttribute($this->created)->readable(true),
            $this->createAttribute($this->modified)->readable(true),
            $this->createAttribute($this->assignedToFbDate)->readable(true),
            $this->createAttribute($this->archivedOrgaName)->readable(true),
            $this->createAttribute($this->archivedVoteUserName)->readable(true),
            $this->createAttribute($this->status)->readable(true),
            $this->createAttribute($this->elementTitle)->readable(true)
                ->aliasedPath($this->element->title),
            $this->createAttribute($this->elementId)->readable(true)
                ->aliasedPath($this->element->id),
            $this->createAttribute($this->paragraphTitle)->readable(true, static fn (StatementFragment $fragment): string => $fragment->getParagraphTitle()),
            $this->createAttribute($this->paragraphId)->readable(true)
                ->aliasedPath($this->paragraph->id),
            $this->createAttribute($this->paragraphParentTitle)->readable(true, static fn (StatementFragment $fragment): string => $fragment->getParagraphParentTitle()),
            $this->createAttribute($this->paragraphParentId)->readable(true)
                ->aliasedPath($this->paragraph->paragraph->id),
            $this->createAttribute($this->documentParentTitle)->readable(true, static fn (StatementFragment $fragment): ?string => $fragment->getDocumentParentTitle()),
            $this->createAttribute($this->documentParentId)->readable(true, static fn (StatementFragment $fragment): ?string => $fragment->getDocumentParentId()),
        ];

        // Only include fields if allowed by permissions. see function cleanFragments()

        if ($this->currentUser->hasPermission('feature_statements_fragment_consideration')) {
            $properties[] = $this->createAttribute($this->consideration)->readable(true);
        }

        if ($this->currentUser->hasPermission('feature_statements_fragment_consideration_advice')) {
            $properties[] = $this->createAttribute($this->considerationAdvice)->readable(true);
        }

        if ($this->currentUser->hasPermission('feature_statements_fragment_vote')) {
            $properties[] = $this->createAttribute($this->vote)->readable(true, static fn (StatementFragment $fragment): ?string => $fragment->getVote());
        }

        if ($this->currentUser->hasPermission('feature_statements_fragment_advice')) {
            $properties[] = $this->createAttribute($this->voteAdvice)->readable(true, function (StatementFragment $fragment): ?string {
                // fragment is currently not assigned to a department (to set advice)
                // and current user has permission to set vote -> voteAdvice needed
                // (the one who set set vote, shall not see voteAdvice until it is completed)
                if (null === $fragment->getDepartmentId() && $this->currentUser->hasPermission('feature_statements_fragment_vote')) {
                    return null;
                }

                return $fragment->getVoteAdvice();
            });
        }

        $properties[] = $this->createToOneRelationship($this->statement)->readable(true);
        $properties[] = $this->createToManyRelationship($this->tags)->readable(true);
        $properties[] = $this->createToOneRelationship($this->department)->readable(true);
        if ($this->currentUser->hasPermission('field_procedure_elements')) {
            $properties[] = $this->createToOneRelationship($this->element)->readable(true);
        }
        $properties[] = $this->createToOneRelationship($this->assignee)->readable(true);
        $properties[] = $this->createToOneRelationship($this->lastClaimedUser)->readable(true)->aliasedPath($this->lastClaimed);

        if ($this->currentUser->hasPermission('field_statement_county')) {
            $properties[] = $this->createToManyRelationship($this->counties)->readable(true);
        }
        if ($this->currentUser->hasPermission('field_statement_priority_area')) {
            $properties[] = $this->createToManyRelationship($this->priorityAreas)->readable(true);
        }
        if ($this->currentUser->hasPermission('field_statement_municipality')) {
            $properties[] = $this->createToManyRelationship($this->municipalities)->readable(true);
        }

        return $properties;
    }

    public function getEntityClass(): string
    {
        return StatementFragment::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_statements_fragment_edit');
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return false;
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->statement->procedure->id),
            $this->conditionFactory->propertyHasValue(false, $this->statement->deleted),
        ];
    }
}
