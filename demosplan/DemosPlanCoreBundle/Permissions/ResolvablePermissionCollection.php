<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use function array_key_exists;

use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionOverrideException;
use DemosEurope\DemosplanAddon\Permission\ResolvablePermissionCollectionInterface;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Every {@link AbstractPermissionEvaluator} will be provided with a **separate** instance of this
 * class. By doing so, they will add their permissions into the instance provided to them, which
 * allows to call .
 */
class ResolvablePermissionCollection implements ResolvablePermissionCollectionInterface, PermissionEvaluatorInterface
{
    /**
     * @var array<non-empty-string, ResolvablePermission>
     */
    protected array $permissions = [];

    private CurrentUserInterface $currentUserProvider;

    private CurrentProcedureService $currentProcedureProvider;

    private CustomerService $currentCustomerProvider;

    private PermissionResolver $permissionResolver;

    private Permissions $corePermissionEvaluator;

    private ValidatorInterface $validator;

    public function __construct(
        CurrentUserInterface $currentUserProvider,
        CurrentProcedureService $currentProcedureProvider,
        CustomerService $currentCustomerProvider,
        PermissionResolver $permissionResolver,
        Permissions $corePermissionEvaluator,
        ValidatorInterface $validator
    ) {
        $this->currentUserProvider = $currentUserProvider;
        $this->currentProcedureProvider = $currentProcedureProvider;
        $this->currentCustomerProvider = $currentCustomerProvider;
        $this->permissionResolver = $permissionResolver;
        $this->corePermissionEvaluator = $corePermissionEvaluator;
        $this->validator = $validator;
    }

    public function isPermissionEnabled(string $permissionName): bool
    {
        if (!$this->isPermissionKnown($permissionName)) {
            return false;
        }

        return $this->permissionResolver->isPermissionEnabled(
            $this->permissions[$permissionName],
            $this->currentUserProvider->getUser(),
            $this->currentProcedureProvider->getProcedure(),
            $this->currentCustomerProvider->getCurrentCustomer()
        );
    }

    public function isPermissionKnown(string $permissionName): bool
    {
        return array_key_exists($permissionName, $this->permissions);
    }

    public function configurePermission(
        string $name,
        string $label,
        string $description,
        bool $exposed,
        array $permissionConditions
    ): void {
        if (false !== $this->corePermissionEvaluator->getPermission($name)) {
            throw new PermissionOverrideException("A permission with the name '$name' is already defined by the core.");
        }

        $permission = new ResolvablePermission($name, $label, $description, $exposed);
        $permission->setConditions($permissionConditions);

        $violations = $this->validator->validate($permission);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $this->permissions[$name] = $permission;
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
