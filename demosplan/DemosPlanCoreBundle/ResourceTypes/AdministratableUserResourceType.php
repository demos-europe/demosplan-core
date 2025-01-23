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

use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\UserResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryUser;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use Elastica\Index;

/**
 * @template-implements ReadableEsResourceTypeInterface<User>
 *
 * @template-extends DplanResourceType<User>
 *
 * 'Administratable' in this context simply means that the accessing user wishes to
 * administrate the accessed resources. It does **not** mean that the {@link User}s covered by this
 * resource type are the only ones that are technically administratable.
 *
 * @property-read End $login
 * @property-read End $email
 * @property-read End $deleted
 * @property-read OrgaResourceType $orga
 * @property-read UserRoleInCustomerResourceType $roleInCustomers
 * @property-read RoleResourceType $roles @deprecated use relation to {@link AdministratableUserResourceType::$roleInCustomers} instead
 */
final class AdministratableUserResourceType extends DplanResourceType implements ReadableEsResourceTypeInterface
{
    public function __construct(private readonly QueryUser $esQuery,
        private readonly JsonApiEsService $jsonApiEsService,
        private readonly UserRepository $userRepository,
        private readonly UserHandler $userHandler)
    {
    }

    public static function getName(): string
    {
        return 'AdministratableUser';
    }

    public function getEntityClass(): string
    {
        return User::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_user_list');
    }

    public function isCreateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_user_add');
    }

    public function isUpdateAllowed(): bool
    {
        return $this->currentUser->hasPermission('feature_user_edit');
    }

    protected function getAccessConditions(): array
    {
        $conditions = [
            // always get non-deleted users only
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            // never show internal Citizen user
            $this->conditionFactory->propertyHasNotValue(User::ANONYMOUS_USER_ID, $this->id),
            // never show ApiAiUser
            $this->conditionFactory->propertyHasNotValue(AiApiUser::AI_API_USER_LOGIN, $this->login),
        ];

        // when user has more role besides RMOPSM s/he may be able to administer
        // more users than only own orga users
        // @improve: T16210
        $user = $this->currentUser->getUser();
        $isOrgaMasterUser = $user->hasRole(Role::ORGANISATION_ADMINISTRATION);
        $isNotPlatformSupport = !$user->hasRole(Role::PLATFORM_SUPPORT);
        if ($isOrgaMasterUser && $isNotPlatformSupport) {
            // only retrieve Users of the organization of the current user
            $orgaId = $user->getOrganisationId();
            $conditions[] = $this->conditionFactory->propertyHasValue(
                $orgaId,
                $this->orga->id
            );
        } else {
            // display only users of current Customer
            $customerId = $this->currentCustomerService->getCurrentCustomer()->getId();
            $conditions[] = $this->conditionFactory->propertyHasValue(
                $customerId,
                $this->roleInCustomers->customer->id
            );
        }

        return $conditions;
    }

    public function getQuery(): AbstractQuery
    {
        return $this->esQuery;
    }

    public function getScopes(): array
    {
        return [];
    }

    public function getSearchType(): Index
    {
        return $this->jsonApiEsService->getElasticaTypeForTypeName(self::getName());
    }

    public function getFacetDefinitions(): array
    {
        return [];
    }

    protected function getProperties(): ResourceConfigBuilderInterface
    {
        $configBuilder = $this->getConfig(UserResourceConfigBuilder::class);

        $configBuilder->id
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->setFilterable();

        $configBuilder->firstname
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathUpdateBehavior()
            ->initializable()
            ->filterable();

        $configBuilder->lastname
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathUpdateBehavior()
            ->addPathCreationBehavior()
            ->setFilterable();

        $configBuilder->email
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->updatable([], function (User $user, string $email): array {
                $user->setEmail($email);

                return [];
            })
            ->initializable()
            ->filterable();

        $configBuilder->profileCompleted
            ->setReadableByCallable(static fn (User $user): bool => $user->isProfileCompleted(), DefaultField::YES)
            ->setSortable();

        $configBuilder->accessConfirmed
            ->setReadableByCallable(static fn (User $user): bool => $user->isAccessConfirmed(), DefaultField::YES)
            ->setSortable();

        $configBuilder->invited
            ->setReadableByCallable(static fn (User $user): bool => $user->isInvited(), DefaultField::YES)
            ->setSortable();

        $configBuilder->newsletter
            ->setReadableByCallable(static fn (User $user): bool => $user->getNewsletter(), DefaultField::YES)
            ->setSortable();

        $configBuilder->noPiwik
            ->setReadableByCallable(static fn (User $user): bool => $user->getNoPiwik(), DefaultField::YES)
            ->setSortable();

        $configBuilder->roles
            ->updatable([], [], function (User $user, array $newRoles): array {
                $roles = $user->getDplanroles($this->currentCustomerService->getCurrentCustomer())->toArray();

                // Remove roles that are not in the new roles array
                $removedRoles = $this->getRemovedRoles($roles, $newRoles);
                foreach ($removedRoles as $role) {
                    $roleInCustomer = $user->removeRoleInCustomer($role, $this->currentCustomerService->getCurrentCustomer());
                    $role->removeUserRoleInCustomer($roleInCustomer);
                    $this->getTypes()->getUserRoleInCustomerResourceType()->deleteEntity($roleInCustomer->getId());
                }

                // Add new roles that the user does not already have
                $addedRoles = $this->getAddedRoles($roles, $newRoles);
                foreach ($addedRoles as $role) {
                    $user->addDplanrole($role, $this->currentCustomerService->getCurrentCustomer());
                }

                return [];
            })
            ->setRelationshipType($this->getTypes()->getRoleResourceType())
            ->readable(true, function (User $user): array {
                $currentCustomer = $this->currentCustomerService->getCurrentCustomer();

                return $user->getRoleInCustomers()
                    ->filter(
                        static fn (UserRoleInCustomer $roleInCustomer): bool => $currentCustomer === $roleInCustomer->getCustomer()
                    )
                    ->map(
                        static fn (UserRoleInCustomer $roleInCustomer): Role => $roleInCustomer->getRole()
                    )
                    ->getValues();
            })
            ->setSortable()
            ->initializable(true, function (User $user, array $roles): array {
                $user->setDplanroles($roles, $this->currentCustomerService->getCurrentCustomer());

                return [];
            });

        $configBuilder->roleInCustomers
            ->setRelationshipType($this->getTypes()->getUserRoleInCustomerResourceType())
            ->setReadableByPath();

        $configBuilder->department
            ->setRelationshipType($this->getTypes()->getDepartmentResourceType())
            ->readable(true, static fn (User $user): ?Department => $user->getDepartment())
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
            })
            ->initializable(false, function (User $user, Department $department): array {
                $user->setDepartment($department);
                $department->addUser($user);

                return [];
            });

        $configBuilder->orga
            ->setRelationshipType($this->getTypes()->getOrgaResourceType())
            ->readable(true, static fn (User $user): ?Orga => $user->getOrga())
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
            })
            ->initializable(false, function (User $user, Orga $orga): array {
                $user->setOrga($orga);
                $orga->addUser($user);

                return [];
            });

        $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(function (User $user, EntityDataInterface $entityData): array {
            $attributes = $entityData->getAttributes();
            $user->setLogin($attributes[$this->email->getAsNamesInDotNotation()]);
            $this->userRepository->persistEntities([$user]);

            $this->userHandler->inviteUser($user);

            return [];
        }));

        return $configBuilder;
    }

    private function getAddedRoles(array $currentRoles, array $newRoles): array
    {
        return array_filter($newRoles, static fn (Role $newRole): bool => !in_array($newRole, $currentRoles, true));
    }

    private function getRemovedRoles(array $currentRoles, array $newRoles): array
    {
        return array_filter($currentRoles, static fn (Role $currentRole): bool => !in_array($currentRole, $newRoles, true));
    }

    public function updateEntity(string $entityId, EntityDataInterface $entityData): ModifiedEntity
    {
        $userAttributes = $entityData->getAttributes();

        if (array_key_exists($this->email->getAsNamesInDotNotation(), $userAttributes)) {
            $modifiedEntity = parent::updateEntity($entityId, $entityData);
            $this->userHandler->inviteUser($modifiedEntity->getEntity());

            return $modifiedEntity;
        }

        return parent::updateEntity($entityId, $entityData);
    }
}
