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

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UserResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use EDT\PathBuilding\End;

/**
 * @template-extends DplanResourceType<UserInterface>
 *
 * @property-read End $firstname
 * @property-read End $lastname
 * @property-read End $deleted
 * @property-read End $profileCompleted
 * @property-read End $accessConfirmed
 * @property-read End $invited
 * @property-read End $newsletter
 * @property-read End $noPiwik
 * @property-read End $login
 * @property-read End $email
 * @property-read UserRoleInCustomerResourceType $roleInCustomers
 * @property-read OrgaResourceType $orga
 * @property-read DepartmentResourceType $department
 * @property-read RoleResourceType $roles
 */
final class UserResourceType extends DplanResourceType implements UserResourceTypeInterface
{
    public function __construct(private readonly ProcedureService $procedureService)
    {
    }

    public function getEntityClass(): string
    {
        return User::class;
    }

    public static function getName(): string
    {
        return 'User';
    }

    public function isAvailable(): bool
    {
        // The existence of the User resource type can be known by the requesting user if
        // any of the following permissions is enabled for the user.
        return $this->currentUser->hasAnyPermissions(
            'area_manage_users',
            'feature_json_api_user',
            'feature_user_list',
            'feature_user_get',
            'feature_user_delete',
            'feature_user_edit',
            'feature_user_add',
        );
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasAllPermissions(
            'feature_user_edit',
            // only allow writing of properties if users can be moved from one
            // orga/department to another (which is currently only needed if the
            // feature_mastertoeblist permission is enabled)
            'feature_mastertoeblist'
        );
    }

    protected function getAccessConditions(): array
    {
        // Without this permission users can use their own User resource only.
        $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
        if ($this->currentUser->hasPermission('area_manage_users')) {
            return [
                $this->conditionFactory->propertyHasValue(
                    $currentCustomer->getId(),
                    $this->roleInCustomers->customer->id
                ),
                $this->conditionFactory->propertyHasNotAnyOfValues(
                    [RoleInterface::API_AI_COMMUNICATOR, RoleInterface::CITIZEN],
                    $this->roleInCustomers->role->code
                ),
                $this->conditionFactory->propertyHasValue(false, $this->deleted),
            ];
        }
        $currentProcedure = $this->currentProcedureService->getProcedure();

        $user = $this->currentUser->getUser();
        $userAuthorized = null === $currentProcedure
            ? false
            : $this->procedureService->isUserAuthorized($currentProcedure->getId());
        if ($userAuthorized) {
            // allow access to all users when working inside a procedure to request assignees of statements or segments
            // TODO: split into AssigneeResourceType to differentiate between claiming and normal (more restricted) user accesses
            return [];
        }

        return [$this->conditionFactory->propertyHasValue($user->getId(), $this->id)];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()
                ->readable()->filterable()->sortable(),
            $this->createAttribute($this->firstname)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->lastname)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->login)->readable(true),
            $this->createAttribute($this->email)->readable(true),
            $this->createAttribute($this->deleted)->filterable()->sortable(),
            $this->createAttribute($this->roleInCustomers)->filterable()->sortable(),
            $this->createAttribute($this->profileCompleted)
                ->readable(true, static fn (User $user): bool => $user->isProfileCompleted()),
            $this->createAttribute($this->accessConfirmed)
                ->readable(true, static fn (User $user): bool => $user->isAccessConfirmed()),
            $this->createAttribute($this->invited)
                ->readable(true, static fn (User $user): bool => $user->isInvited()),
            $this->createAttribute($this->newsletter)
                ->readable(true, static fn (User $user): bool => $user->getNewsletter()),
            $this->createAttribute($this->noPiwik)
                ->readable(true, static fn (User $user): bool => $user->getNoPiwik()),
            $this->createToManyRelationship($this->roles)
                ->readable(true, static function (User $user): array {
                    $roles = [];
                    $roleRelations = $user->getRoleInCustomers()->toArray();
                    $currentCustomer = $user->getCurrentCustomer();
                    /** @var UserRoleInCustomer $roleRelation */
                    foreach ($roleRelations as $roleRelation) {
                        if ($currentCustomer === $roleRelation->getCustomer()) {
                            $roles[] = $roleRelation->getRole();
                        }
                    }

                    return $roles;
                }, true),
            $this->createToOneRelationship($this->department)
                ->readable(true, static fn (User $user) => $user->getDepartment(), true)
                ->updatable([], [], static function (User $user, Department $newDepartment): array {
                    // Special logic for moving users from one department into another
                    $originalDepartment = $user->getDepartment();
                    if ($originalDepartment instanceof Department) {
                        $originalDepartment->setGwId(null);
                        $originalDepartment->removeUser($user);
                    }
                    $user->setDepartment($newDepartment);
                    $newDepartment->addUser($user);

                    return [];
                }),
            $this->createToOneRelationship($this->orga)
                ->readable(true, static fn (User $user): ?OrgaInterface => $user->getOrga(), true)
                ->updatable([], [], static function (User $user, Orga $newOrga): array {
                    // Special logic for moving users from one organization into another
                    $originalOrga = $user->getOrga();
                    if ($originalOrga instanceof Orga) {
                        $originalOrga->setGwId(null);
                        $originalOrga->removeUser($user);
                    }
                    $user->setOrga($newOrga);
                    $newOrga->addUser($user);

                    return [];
                }),
        ];
    }
}
