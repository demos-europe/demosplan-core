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

use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathException;

/**
 * @template-extends DplanResourceType<Role>
 *
 * @property-read End $ident
 * @property-read End $name
 * @property-read End $groupCode
 * @property-read End $groupName
 * @property-read End $code
 */
final class RoleResourceType extends DplanResourceType
{
    public static function getName(): string
    {
        return 'Role';
    }

    public function getEntityClass(): string
    {
        return Role::class;
    }

    public function getIdentifierPropertyPath(): array
    {
        return $this->ident->getAsNames();
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function isGetAllowed(): bool
    {
        return false;
    }

    public function isListAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_user_list_extended');
    }

    /**
     * @throws PathException
     */
    protected function getAccessConditions(): array
    {
        $projectRoleCodes = $this->globalConfig->getRolesAllowed();

        return [[] === $projectRoleCodes
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasAnyOfValues($projectRoleCodes, $this->code)];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()
                ->readable()
                ->filterable()
                ->sortable()
                ->aliasedPath($this->ident),
            $this->createAttribute($this->code)
                ->readable(true)
                ->filterable()
                ->sortable(),
            $this->createAttribute($this->groupCode)
                ->readable(true)
                ->filterable()
                ->sortable(),
            $this->createAttribute($this->groupName)
                ->readable(true)
                ->filterable()
                ->sortable(),
            $this->createAttribute($this->name)
                ->readable(true, static fn (Role $role): string =>
                    // Role->name is no longer found in database. It is added on doctrine postLoad
                    // event via RoleEntityListener. This allows the use of correctly translated
                    // names, but it can't be filtered or sorted at the moment.
                    $role->getName()),
        ];
    }
}
