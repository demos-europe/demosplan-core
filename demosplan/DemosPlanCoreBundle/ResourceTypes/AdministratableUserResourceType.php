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

use DemosEurope\DemosplanAddon\EntityPath\Paths;
use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\ResourceConfigBuilder\UserResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryUser;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\PathBuilding\End;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToMany\CallbackToManyRelationshipSetBehavior;
use EDT\Wrapping\PropertyBehavior\Relationship\ToOne\CallbackToOneRelationshipSetBehavior;
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
 * @property-read End $deleted
 * @property-read OrgaResourceType $orga
 * @property-read UserRoleInCustomerResourceType $roleInCustomers
 * @property-read RoleResourceType $roles @deprecated use relation to {@link AdministratableUserResourceType::$roleInCustomers} instead
 */
final class AdministratableUserResourceType extends DplanResourceType implements ReadableEsResourceTypeInterface
{
    public function __construct(private readonly QueryUser $esQuery, private readonly JsonApiEsService $jsonApiEsService, private readonly UserRepository $userRepository)
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
        return $this->currentUser->hasAllPermissions(
            'feature_user_edit',
            'feature_json_api_update'
        );
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
            ->updatable()
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathCreationBehavior()
            ->setFilterable();

        $configBuilder->lastname
            ->updatable()
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathCreationBehavior()
            ->setFilterable();

        $configBuilder->login
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathCreationBehavior()
            ->setFilterable();

        $configBuilder->email
            ->updatable()
            ->setReadableByPath(DefaultField::YES)
            ->setSortable()
            ->addPathCreationBehavior()
            ->setFilterable();

        $configBuilder->profileCompleted
            ->setSortable()
            ->setReadableByCallable(static fn (User $user): bool => $user->isProfileCompleted(), DefaultField::YES);

        $configBuilder->accessConfirmed
            ->setReadableByCallable(static fn (User $user): bool => $user->isAccessConfirmed(), DefaultField::YES);

        $configBuilder->invited
            ->setReadableByCallable(static fn (User $user): bool => $user->isInvited(), DefaultField::YES);

        $configBuilder->newsletter
            ->setReadableByCallable(static fn (User $user): bool => $user->getNewsletter(), DefaultField::YES);

        $configBuilder->noPiwik
            ->setReadableByCallable(static fn (User $user): bool => $user->getNoPiwik(), DefaultField::YES);

        $configBuilder->roles
            // ->setAliasedPath(Paths::user()->roleInCustomers)
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
            }, DefaultField::YES)
            ->setRelationshipType($this->getTypes()->getRoleResourceType())
            ->addCreationBehavior(
                CallbackToManyRelationshipSetBehavior::createFactory(function (User $user, array $roles): array {
                    $user->setDplanroles($roles, $this->currentCustomerService->getCurrentCustomer());

                    return [];
                }, [], OptionalField::NO, [])
            );

        $configBuilder->roleInCustomers
            ->setRelationshipType($this->getTypes()->getUserRoleInCustomerResourceType())
            ->setReadableByPath(DefaultField::YES);

        $configBuilder->department
            ->setReadableByCallable(static fn (User $user): ?Department => $user->getDepartment(), DefaultField::YES)
            ->setRelationshipType($this->getTypes()->getDepartmentResourceType())
            ->addCreationBehavior(
                CallbackToOneRelationshipSetBehavior::createFactory(function (User $user, Department $department): array {
                    $user->setDepartment($department);
                    $department->addUser($user);

                    return [];
                }, [], OptionalField::NO, [])
            );

        $configBuilder->orga
            ->setReadableByCallable(static fn (User $user): ?Orga => $user->getOrga(), DefaultField::YES)
            ->setRelationshipType($this->getTypes()->getOrgaResourceType())
            ->addCreationBehavior(
                CallbackToOneRelationshipSetBehavior::createFactory(function (User $user, Orga $orga): array {
                    $user->setOrga($orga);
                    $orga->addUser($user);

                    return [];
                }, [], OptionalField::NO, [])
            );

        $configBuilder->addPostConstructorBehavior(new FixedSetBehavior(function (User $user, EntityDataInterface $entityData): array {
            $this->userRepository->persistEntities([$user]);

            return [];
        }));

        return $configBuilder;
    }
}
