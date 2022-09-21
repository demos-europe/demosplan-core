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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\UserFilterSet;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\FunctionInterface;

/**
 * @template-extends DplanResourceType<UserFilterSet>
 *
 * @property-read End $name
 * @property-read HashedQueryResourceType $filterSet
 * @property-read ProcedureResourceType $procedure
 * @property-read UserResourceType $user
 */
class UserFilterSetResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'UserFilterSet';
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true),
            $this->createAttribute($this->name)->readable(true),
            $this->createToOneRelationship($this->filterSet)->readable(true),
        ];
    }

    public function getEntityClass(): string
    {
        return UserFilterSet::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'area_admin_assessmenttable',
            'feature_procedure_user_filter_sets'
        );
    }

    public function isReferencable(): bool
    {
        return false;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    public function getAccessCondition(): FunctionInterface
    {
        $user = $this->currentUser->getUser();
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyHasValue($user->getId(), ...$this->user->id),
            $this->conditionFactory->propertyHasValue($procedure->getId(), ...$this->procedure->id)
        );
    }
}
