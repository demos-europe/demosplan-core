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

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\QueryUser;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;
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
 * @property-read End $firstname
 * @property-read End $lastname
 * @property-read End $login
 * @property-read End $email
 * @property-read End $deleted
 * @property-read End $profileCompleted
 * @property-read End $accessConfirmed
 * @property-read End $invited
 * @property-read End $newsletter
 * @property-read End $noPiwik
 * @property-read DepartmentResourceType $department
 * @property-read OrgaResourceType $orga
 * @property-read UserRoleInCustomerResourceType $roleInCustomers
 * @property-read RoleResourceType $roles @deprecated use relation to {@link AdministratableUserResourceType::$roleInCustomers} instead
 */
final class AdministratableUserResourceType extends DplanResourceType implements ReadableEsResourceTypeInterface
{
    /**
     * @var QueryUser
     */
    private $esQuery;

    /**
     * @var JsonApiEsService
     */
    private $jsonApiEsService;

    public function __construct(QueryUser $esQuery, JsonApiEsService $jsonApiEsService)
    {
        $this->esQuery = $esQuery;
        $this->jsonApiEsService = $jsonApiEsService;
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

    public function getAccessCondition(): PathsBasedInterface
    {
        $conditions = [
            // always get non-deleted users only
            $this->conditionFactory->propertyHasValue(false, $this->deleted),
            // never show internal Citizen user
            $this->conditionFactory->propertyHasNotValue(User::ANONYMOUS_USER_ID, $this->id),
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

        return $this->conditionFactory->allConditionsApply(...$conditions);
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
            $this->createAttribute($this->firstname)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->lastname)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->login)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->email)->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->profileCompleted)
                ->readable(true, static function (User $user): bool {
                    return $user->isProfileCompleted();
                }),
            $this->createAttribute($this->accessConfirmed)
                ->readable(true, static function (User $user): bool {
                    return $user->isAccessConfirmed();
                }),
            $this->createAttribute($this->invited)
                ->readable(true, static function (User $user): bool {
                    return $user->isInvited();
                }),
            $this->createAttribute($this->newsletter)
                ->readable(true, static function (User $user): bool {
                    return $user->getNewsletter();
                }),
            $this->createAttribute($this->noPiwik)
                ->readable(true, static function (User $user): bool {
                    return $user->getNoPiwik();
                }),
            $this->createToManyRelationship($this->roles)
                // Send only the user roles for the current customer.
                ->readable(true, function (User $user): array {
                    $currentCustomer = $this->currentCustomerService->getCurrentCustomer();

                    return $user->getRoleInCustomers()
                        ->filter(
                            static function (UserRoleInCustomer $roleInCustomer) use ($currentCustomer): bool {
                                return $currentCustomer === $roleInCustomer->getCustomer();
                            }
                        )
                        ->map(
                            static function (UserRoleInCustomer $roleInCustomer): Role {
                                return $roleInCustomer->getRole();
                            }
                        )
                        ->getValues();
                }),
            $this->createToOneRelationship($this->department)
                ->readable(true, static function (User $user): ?Department {
                    return $user->getDepartment();
                }),
            $this->createToOneRelationship($this->orga)
                ->readable(true, static function (User $user): ?Orga {
                    return $user->getOrga();
                }),
        ];
    }
}
