<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use DemosEurope\DemosplanAddon\Permission\PermissionConditionBuilder;
use DemosEurope\DemosplanAddon\Permission\PermissionMetaInterface;
use DemosEurope\DemosplanAddon\Permission\ResolvablePermissionCollectionInterface;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Every {@link AbstractPermissionEvaluator} will be provided with a **separate** instance of this
 * class. By doing so, they will add their permissions into the instance provided to them, which
 * allows to call .
 */
class ResolvablePermissionCollection implements ResolvablePermissionCollectionInterface
{
    /**
     * @var array<non-empty-string, ResolvablePermission>
     */
    protected array $permissions = [];

    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @param non-empty-string $permissionName
     */
    public function getResolvablePermission(string $permissionName): ?ResolvablePermission
    {
        return $this->permissions[$permissionName] ?? null;
    }

    public function configurePermission(
        string $name,
        string $label,
        string $description,
        bool $exposed,
        array $permissionConditions
    ): void {
        $permission = new ResolvablePermission($name, $label, $description, $exposed);
        $permission->setConditions($permissionConditions);

        $violations = $this->validator->validate($permission);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $this->permissions[$name] = $permission;
    }

    public function configurePermissionInstance(
        PermissionMetaInterface $permission,
        PermissionConditionBuilder $permissionConditionBuilder
    ): void {
        $this->configurePermission(
            $permission->getPermissionName(),
            $permission->getLabel(),
            $permission->getDescription(),
            $permission->isExposed(),
            $permissionConditionBuilder->build()
        );
    }

    /**
     * @return array<non-empty-string, Permission>
     */
    public function getPermissions(): array
    {
        return array_map(
            static fn (
                ResolvablePermission $permission
            ): Permission => Permission::instanceFromArray($permission->getName(), [
                'label'         => $permission->getLabel(),
                'expose'        => $permission->isExposed(),
                'loginRequired' => false,
                'description'   => $permission->getDescription(),
            ]),
            $this->permissions
        );
    }
}
