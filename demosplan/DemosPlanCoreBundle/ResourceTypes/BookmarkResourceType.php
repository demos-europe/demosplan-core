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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Bookmark;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<Bookmark>
 *
 * @property-read End $name
 * @property-read HashedQueryResourceType $filterSet
 * @property-read ProcedureResourceType $procedure
 * @property-read UserResourceType $user
 */
class BookmarkResourceType extends DplanResourceType
{
    /**
     * Deliberately still `UserFilterSet`, even though the class and entity are now named `Bookmark`:
     * this is the resource type identifier the assessment table requests by string over the API 2.0
     * endpoint (see `client/js/store/statement/Filter.js`). Renaming it would break that frontend,
     * which is out of scope until the assessment table moves off the EDT resource types.
     */
    public static function getName(): string
    {
        return 'UserFilterSet';
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable(),
            $this->createAttribute($this->name)->readable(true),
            $this->createToOneRelationship($this->filterSet)->readable(true),
        ];
    }

    public function getEntityClass(): string
    {
        return Bookmark::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_admin_assessmenttable',
            'feature_procedure_user_filter_sets'
        );
    }

    protected function getAccessConditions(): array
    {
        $user = $this->currentUser->getUser();
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue($user->getId(), $this->user->id),
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
        ];
    }
}
