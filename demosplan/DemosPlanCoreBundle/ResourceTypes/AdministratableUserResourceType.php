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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\UserResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryUser;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\RequestHandling\ModifiedEntity;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\CallbackAttributeSetBehavior;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToMany\CallbackToManyRelationshipSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\CallbackToOneRelationshipSetBehavior;
use Elastica\Index;
use InvalidArgumentException;

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
 * @property-read End $canManageProcedures
 * @property-read End $procedureCreationEnabledForOrga
 * @property-read OrgaResourceType $orga
 * @property-read UserRoleInCustomerResourceType $roleInCustomers
 * @property-read RoleResourceType $roles @deprecated use relation to {@link AdministratableUserResourceType::$roleInCustomers} instead
 */
final class AdministratableUserResourceType extends DplanResourceType implements ReadableEsResourceTypeInterface
{
    /**
     * Roles for which the individual procedure-creation permission ($canManageProcedures) is applicable.
     *
     * @var list<non-empty-string>
     */
    private const PROCEDURE_MANAGEMENT_ROLE_CODES = [
        RoleInterface::PLANNING_AGENCY_ADMIN,
        RoleInterface::HEARING_AUTHORITY_ADMIN,
    ];

    public function __construct(private readonly QueryUser $esQuery,
        private readonly JsonApiEsService $jsonApiEsService,
        private readonly UserRepository $userRepository,
        private readonly UserHandler $userHandler,
        private readonly AccessControlService $accessControlService,
        private readonly RoleHandler $roleHandler,
        private readonly UserAccessControlService $userAccessControlService)
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

    public function isDeleteAllowed(): bool
    {
        return $this->currentUser->hasPermission('area_manage_users');
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
            ->setReadableByPath()
            ->setSortable()
            ->setFilterable();

        $configBuilder->firstname
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathUpdateBehavior()
            ->addPathCreationBehavior()
            ->setFilterable();

        $configBuilder->lastname
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathUpdateBehavior()
            ->addPathCreationBehavior()
            ->setFilterable();

        $configBuilder->email
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathUpdateBehavior()
            ->addPathCreationBehavior()
            ->setFilterable();

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

        // Whether this specific user has been individually granted the right to create/manage procedures
        // (as RMOPSA or RMOPHA), independent of the organisation-wide grant. Only meaningful while the
        // organisation-wide grant (see $procedureCreationEnabledForOrga) is disabled for that role.
        $configBuilder->canManageProcedures
            ->setReadableByCallable(
                function (User $user): bool {
                    $customer = $user->getCurrentCustomer();
                    if (!$customer instanceof CustomerInterface) {
                        return false;
                    }

                    foreach ($this->getUserProcedureManagementRoleCodes($user, $customer) as $roleCode) {
                        $role = $this->roleHandler->getRoleByCode($roleCode);
                        if ($role instanceof RoleInterface
                            && $this->userAccessControlService->userPermissionExists($user, AccessControlService::CREATE_PROCEDURES_PERMISSION, $role)) {
                            return true;
                        }
                    }

                    return false;
                },
                DefaultField::YES
            )
            ->addUpdateBehavior(
                CallbackAttributeSetBehavior::createFactory(
                    [],
                    // Intentionally a no-op: this attribute must merely be accepted by EDT here so it
                    // is part of the request payload. The actual grant/removal happens in updateEntity(),
                    // executed after the roles relationship behavior, so the user's final (post-update)
                    // role set is what gets evaluated instead of depending on undocumented behavior order.
                    static fn (User $user, bool $canManageProcedures): array => [],
                    OptionalField::YES
                )
            );

        // Whether the organisation this user belongs to already grants procedure-creation rights to
        // every user of this user's RMOPSA/RMOPHA role in it (org-wide `access_control` grant). While
        // true (for a role this user has), per-user configuration via $canManageProcedures has no
        // effect for that role and should not be offered as editable in the UI.
        $configBuilder->procedureCreationEnabledForOrga
            ->setReadableByCallable(
                function (User $user): bool {
                    $orga = $user->getOrga();
                    $customer = $user->getCurrentCustomer();
                    if (!$orga instanceof OrgaInterface || !$customer instanceof CustomerInterface) {
                        return false;
                    }

                    $roleCodes = $this->getUserProcedureManagementRoleCodes($user, $customer);
                    if ([] === $roleCodes) {
                        return false;
                    }

                    return $this->accessControlService->permissionExist(
                        AccessControlService::CREATE_PROCEDURES_PERMISSION,
                        $orga,
                        $customer,
                        $roleCodes
                    );
                },
                DefaultField::YES
            );

        $configBuilder->roles
            ->addUpdateBehavior(
                CallbackToManyRelationshipSetBehavior::createFactory(function (User $user, array $newRoles): array {
                    $this->updateRoles($user, $newRoles);

                    return [];
                },
                    [],
                    OptionalField::YES,
                    [])
            )
            ->setRelationshipType($this->getTypes()->getRoleResourceType())
            ->setReadableByCallable(function (User $user): array {
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
            ->addCreationBehavior(
                CallbackToManyRelationshipSetBehavior::createFactory(function (User $user, array $roles): array {
                    $user->setDplanroles($roles, $this->currentCustomerService->getCurrentCustomer());

                    return [];
                }, [], OptionalField::NO, [])
            );

        $configBuilder->roleInCustomers
            ->setRelationshipType($this->getTypes()->getUserRoleInCustomerResourceType())
            ->setReadableByPath();

        $configBuilder->department
            ->setRelationshipType($this->getTypes()->getDepartmentResourceType())
            ->setReadableByCallable(static fn (User $user): ?Department => $user->getDepartment(), DefaultField::YES)
            ->addUpdateBehavior(
                CallbackToOneRelationshipSetBehavior::createFactory(function (User $user, Department $newDepartment): array {
                    // Special logic for moving users from one department into another
                    $originalDepartment = $user->getDepartment();
                    if ($originalDepartment instanceof Department) {
                        $originalDepartment->setGwId(null);
                        $originalDepartment->removeUser($user);
                    }
                    $user->setDepartment($newDepartment);
                    $newDepartment->addUser($user);

                    return [];
                },
                    [],
                    OptionalField::YES,
                    [])
            )
            ->addCreationBehavior(
                CallbackToOneRelationshipSetBehavior::createFactory(static function (User $user, Department $department): array {
                    $user->setDepartment($department);
                    $department->addUser($user);

                    return [];
                }, [], OptionalField::NO, [])
            );

        $configBuilder->orga
            ->setRelationshipType($this->getTypes()->getOrgaResourceType())
            ->setReadableByCallable(static fn (User $user): ?Orga => $user->getOrga(), DefaultField::YES)
            ->addUpdateBehavior(
                CallbackToOneRelationshipSetBehavior::createFactory(function (User $user, Orga $newOrga): array {
                    // Special logic for moving users from one organization into another
                    $originalOrga = $user->getOrga();
                    if ($originalOrga instanceof Orga) {
                        $originalOrga->setGwId(null);
                        $originalOrga->removeUser($user);
                    }
                    $user->setOrga($newOrga);
                    $newOrga->addUser($user);

                    return [];
                },
                    [],
                    OptionalField::YES,
                    [])
            )
            ->addCreationBehavior(
                CallbackToOneRelationshipSetBehavior::createFactory(static function (UserInterface $user, OrgaInterface $orga): array {
                    $user->setOrga($orga);
                    $orga->addUser($user);

                    return [];
                }, [], OptionalField::NO, [])
            );

        $configBuilder->addCreationBehavior(new FixedSetBehavior(function (User $user, EntityDataInterface $entityData): array {
            if ($this->currentUser->hasPermission('feature_organisation_own_users_list')) {
                $orgaToSet = $entityData->getToOneRelationships()[$this->orga->getAsNamesInDotNotation()];
                if ($this->currentUser->getUser()->getOrga()->getId() !== $orgaToSet['id']) {
                    $this->messageBag->add('error', 'error.user.administration.limited.to.own.organisation');
                    throw new InvalidArgumentException('User is only allowed to administrate users of their own organisation.');
                }
            }
            $attributes = $entityData->getAttributes();
            $user->setLogin($attributes[$this->email->getAsNamesInDotNotation()]);
            $user->setEmail($attributes[$this->email->getAsNamesInDotNotation()]);
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

        // Executed once, after the roles relationship (if present in the same request) has already
        // been applied, so the user's final role set is what gets evaluated below.
        $modifiedEntity = parent::updateEntity($entityId, $entityData);

        if (array_key_exists($this->email->getAsNamesInDotNotation(), $userAttributes)) {
            $this->userHandler->inviteUser($modifiedEntity->getEntity());
        }

        if (array_key_exists($this->canManageProcedures->getAsNamesInDotNotation(), $userAttributes)) {
            $this->updateCanManageProcedures(
                $modifiedEntity->getEntity(),
                (bool) $userAttributes[$this->canManageProcedures->getAsNamesInDotNotation()]
            );
        }

        return $modifiedEntity;
    }

    /**
     * Grants or removes the individual procedure-creation permission for this user, for each of RMOPSA/RMOPHA
     * the user currently holds.
     *
     * Per role, no-ops if the user does not (or no longer) have that role, or if the organisation already
     * grants procedure-creation org-wide for it — in that case per-user configuration must first be unlocked
     * by disabling the organisation-wide grant for that role, so a request cannot bypass that precondition.
     */
    private function updateCanManageProcedures(UserInterface $user, bool $canManageProcedures): void
    {
        $orga = $user->getOrga();
        $customer = $user->getCurrentCustomer();
        if (!$orga instanceof OrgaInterface || !$customer instanceof CustomerInterface) {
            return;
        }

        foreach ($this->getUserProcedureManagementRoleCodes($user, $customer) as $roleCode) {
            $role = $this->roleHandler->getRoleByCode($roleCode);
            if (!$role instanceof RoleInterface) {
                continue;
            }

            if ($this->accessControlService->permissionExist(AccessControlService::CREATE_PROCEDURES_PERMISSION, $orga, $customer, [$roleCode])) {
                continue;
            }

            if ($canManageProcedures) {
                $this->userAccessControlService->createUserPermission($user, AccessControlService::CREATE_PROCEDURES_PERMISSION, $role);
            } else {
                $this->userAccessControlService->removeUserPermission($user, AccessControlService::CREATE_PROCEDURES_PERMISSION, $role);
            }
        }
    }

    /**
     * @return list<non-empty-string> the role codes among self::PROCEDURE_MANAGEMENT_ROLE_CODES that this user
     *                                currently holds for the given customer
     */
    private function getUserProcedureManagementRoleCodes(UserInterface $user, CustomerInterface $customer): array
    {
        $userRoleCodes = $user->getDplanroles($customer)->map(
            static fn (RoleInterface $role): string => $role->getCode()
        )->toArray();

        return array_values(array_intersect(self::PROCEDURE_MANAGEMENT_ROLE_CODES, $userRoleCodes));
    }

    public function deleteEntity(string $userId): void
    {
        $nullEqualsSucceed = $this->userHandler->wipeUsersById([$userId]);
        if (null !== $nullEqualsSucceed) {
            // messageBag for errors has been filled already
            throw new InvalidArgumentException(sprintf('Soft-deleting user with id %s failed via AdministratableUserResourceType', $userId));
        }
        // messageBag with confirmation has been filled already
    }

    private function updateRoles(UserInterface $user, array $newRoles): void
    {
        $roles = $user->getDplanroles($this->currentCustomerService->getCurrentCustomer())->toArray();

        // Remove roles that are not in the new roles array
        $removedRoles = $this->getRemovedRoles($roles, $newRoles);
        foreach ($removedRoles as $role) {
            $roleInCustomer = $user->removeRoleInCustomer($role, $this->currentCustomerService->getCurrentCustomer());
            $role->removeUserRoleInCustomer($roleInCustomer);
            $this->getTypes()->getUserRoleInCustomerResourceType()->deleteEntity($roleInCustomer->getId());

            // The user no longer has this role, so any individually granted permission tied to it
            // (e.g. RMOPSA's procedure-creation right) is stale and must not be left behind.
            $this->userAccessControlService->removeUserPermission($user, AccessControlService::CREATE_PROCEDURES_PERMISSION, $role);
        }

        // Add new roles that the user does not already have
        $addedRoles = $this->getAddedRoles($roles, $newRoles);
        foreach ($addedRoles as $role) {
            $user->addDplanrole($role, $this->currentCustomerService->getCurrentCustomer());
        }
    }
}
