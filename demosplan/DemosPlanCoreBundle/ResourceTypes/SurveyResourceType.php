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

use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Survey>
 *
 * @property-read End                    $description
 * @property-read End                    $endDate
 * @property-read End                    $startDate
 * @property-read End                    $status
 * @property-read End                    $title
 * @property-read ProcedureResourceType  $procedure
 * @property-read SurveyVoteResourceType $votes
 */
final class SurveyResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Survey';
    }

    public function getEntityClass(): string
    {
        return Survey::class;
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
            $this->createIdentifier()->readable()->sortable()->filterable(),
            $this->createAttribute($this->description)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->endDate)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->startDate)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->status)->readable(true)->sortable()->filterable(),
            $this->createAttribute($this->title)->readable(true)->sortable()->filterable(),
            $this->createToOneRelationship($this->procedure)->readable()->sortable()->filterable(),
            $this->createToManyRelationship($this->votes)->readable()->sortable()->filterable(),
        ];
    }
}
