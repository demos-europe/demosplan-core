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

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\FunctionInterface;

/**
 * @template-extends DplanResourceType<Department>
 *
 * @property-read End $name
 * @property-read AssignableUserResourceType $user
 */
final class DepartmentResourceType extends DplanResourceType
{
    public function getEntityClass(): string
    {
        return Department::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasAnyPermissions(
            // Resource is needed for moving users from one department to another, which is only needed
            // if the feature_mastertoeblist permission is enabled.
            'feature_mastertoeblist',
            // Managing users includes access to their departments
            'area_manage_users',
            // Departments are included in the response when fragments are updated
            'feature_statements_fragment_edit'
        );
    }

    public static function getName(): string
    {
        return 'Department';
    }

    public function getAccessCondition(): FunctionInterface
    {
        return $this->conditionFactory->true();
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->name)->readable(true)->filterable()->sortable(),
        ];
    }
}
