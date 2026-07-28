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

use demosplan\DemosPlanCoreBundle\Api\AssignableUser\AccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<User>
 *
 * @property-read End $firstname
 * @property-read End $lastname
 * @property-read DepartmentResourceType $department
 * @property-read OrgaResourceType $orga
 */
final class AssignableUserResourceType extends DplanResourceType
{
    public function __construct(private readonly AccessChecker $accessChecker)
    {
    }

    public static function getName(): string
    {
        return 'AssignableUser';
    }

    public function getEntityClass(): string
    {
        return User::class;
    }

    public function isAvailable(): bool
    {
        return $this->accessChecker->isAvailable();
    }

    protected function getAccessConditions(): array
    {
        return $this->accessChecker->getAccessConditions();
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->filterable()->sortable(),
            $this->createAttribute($this->firstname)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->lastname)->readable(true)->filterable()->sortable(),
            $this->createToOneRelationship($this->department)->readable()->filterable()->sortable(),
            $this->createToOneRelationship($this->orga)
                ->readable(true, fn (User $user): ?Orga => $user->getOrga(), true)
                ->filterable()
                ->sortable(),
        ];
    }
}
