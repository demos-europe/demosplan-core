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

use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<SurveyVote>
 *
 * @property-read End                $isAgreed
 * @property-read End                $text
 * @property-read End                $textReview
 * @property-read End                $hasText
 * @property-read End                $hasApprovedText
 * @property-read End                $getTextReviewAllowedValues
 * @property-read End                $createdDate
 * @property-read UserResourceType   $user
 * @property-read SurveyResourceType $survey
 */
final class SurveyVoteResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'SurveyVote';
    }

    public function getEntityClass(): string
    {
        return SurveyVote::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_survey');
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
        return [];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()
                ->readable()->filterable()->sortable(),
            $this->createAttribute($this->isAgreed)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->text)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->textReview)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->createdDate)
                ->readable(true, fn (SurveyVote $surveyVote): string => $this->formatDate($surveyVote->getCreatedDate())),
            $this->createAttribute($this->hasText)
                ->readable(true, static fn (SurveyVote $surveyVote): bool => $surveyVote->hasText()),
            $this->createAttribute($this->hasApprovedText)
                ->readable(true, static fn (SurveyVote $surveyVote): bool => $surveyVote->hasApprovedText()),
            $this->createAttribute($this->getTextReviewAllowedValues)
                ->readable(true, [SurveyVote::class, 'getTextReviewAllowedValues']),
            $this->createToOneRelationship($this->user)->readable()->filterable()->sortable(),
            $this->createToOneRelationship($this->survey)->readable(),
        ];
    }
}
