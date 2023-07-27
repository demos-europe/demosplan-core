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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\UpdatableDqlResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\UserResourceTypeInterface;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<User>
 *
 * @template-extends DplanResourceType<User>
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
final class UserResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface, UserResourceTypeInterface
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

    public function updateObject(object $entity, array $properties): ResourceChange
    {
        // Special logic for moving users from one orga/department into another

        /** @var User $entity */
        $orgaUpdate = array_key_exists($this->orga->getAsNamesInDotNotation(), $properties);
        if ($orgaUpdate) {
            $originalOrga = $entity->getOrga();
            if ($originalOrga instanceof Orga) {
                $originalOrga->setGwId(null);
                $originalOrga->removeUser($entity);
            }
        }

        $departmentUpdate = array_key_exists($this->department->getAsNamesInDotNotation(), $properties);
        if ($departmentUpdate) {
            $originalDepartment = $entity->getDepartment();
            if ($originalDepartment instanceof Department) {
                $originalDepartment->setGwId(null);
                $originalDepartment->removeUser($entity);
            }
        }

        $resourceChange = new ResourceChange($entity, $this, $properties);

        $this->resourceTypeService->updateObjectNaive($entity, $properties);
        $this->resourceTypeService->validateObject($entity);

        if (isset($originalOrga) && $originalOrga instanceof Orga) {
            $newOrga = $entity->getOrga();
            if (null !== $newOrga) {
                $newOrga->addUser($entity);
            }
            $resourceChange->addEntityToPersist($newOrga);
            $resourceChange->addEntityToPersist($originalOrga);
        }
        if (isset($originalDepartment) && $originalDepartment instanceof Department) {
            $newDepartment = $entity->getDepartment();
            if (null !== $newDepartment) {
                $newDepartment->addUser($entity);
            }
            $resourceChange->addEntityToPersist($newDepartment);
            $resourceChange->addEntityToPersist($originalDepartment);
        }

        return $resourceChange;
    }

    /**
     * @return array<string,string|null>
     *
     * @throws UserNotFoundException
     */
    public function getUpdatableProperties(object $targetEntity): array
    {
        // only allow writing of these properties if users can be moved from one
        // orga/department to another (which is currently only needed if the
        // feature_mastertoeblist permission is enabled)
        if ($this->currentUser->hasPermission('feature_mastertoeblist')) {
            return $this->toProperties(
                $this->orga,
                $this->department
            );
        }

        return [];
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

    public function getAccessCondition(): PathsBasedInterface
    {
        // Without this permission users can use their own User resource only.
        if ($this->currentUser->hasPermission('area_manage_users')) {
            return $this->conditionFactory->true();
        }
        $currentProcedure = $this->currentProcedureService->getProcedure();
        $user = $this->currentUser->getUser();
        $userAuthorized = null === $currentProcedure
            ? false
            : $this->procedureService->isUserAuthorized($currentProcedure->getId());
        if ($userAuthorized) {
            // allow access to all users when working inside a procedure to request assignees of statements or segments
            // TODO: split into AssigneeResourceType to differentiate between claiming and normal (more restricted) user accesses
            return $this->conditionFactory->true();
        }

        return $this->conditionFactory->propertyHasValue($user->getId(), $this->id);
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
            $this->createAttribute($this->id)
                ->readable(true)->filterable()->sortable(),
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
            $this->createToManyRelationship($this->roles, true)
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
                }),
            $this->createToOneRelationship($this->department, true)
                ->readable(true, static fn (User $user) => $user->getDepartment()),
            $this->createToOneRelationship($this->orga, true)
                ->readable(true, static fn (User $user) => $user->getOrga()),
        ];
    }
}
