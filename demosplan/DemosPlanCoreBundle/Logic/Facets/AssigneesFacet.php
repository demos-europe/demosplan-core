<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Facets;

use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet\GroupedFacetInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AssignableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\DepartmentResourceType;
use EDT\Querying\Contracts\FunctionInterface;

/**
 * @template-implements GroupedFacetInterface<User,Department>
 */
class AssigneesFacet implements GroupedFacetInterface
{
    private $rootItemsLoadCondition;

    /**
     * @param FunctionInterface<bool> $rootItemsLoadCondition
     */
    public function __construct(FunctionInterface $rootItemsLoadCondition)
    {
        $this->rootItemsLoadCondition = $rootItemsLoadCondition;
    }

    public function getFacetNameTranslationKey(): string
    {
        return 'assignee';
    }

    public function getGroupsResourceType(): string
    {
        return DepartmentResourceType::getName();
    }

    public function getItemsResourceType(): string
    {
        return AssignableUserResourceType::getName();
    }

    /**
     * @param Department $group
     */
    public function getGroupItems(object $group): array
    {
        return $group->getUsers()->all();
    }

    /**
     * @param User $item
     */
    public function getItemIdentifier(object $item): string
    {
        return $item->getId();
    }

    /**
     * @param Department $group
     */
    public function getGroupIdentifier(object $group): string
    {
        return $group->getId();
    }

    /**
     * @param Department $group
     */
    public function getGroupTitle(object $group): string
    {
        return $group->getName();
    }

    public function getGroupsLoadConditions(): array
    {
        return [];
    }

    /**
     * @param User $item
     */
    public function getItemTitle(object $item): string
    {
        return $item->getLastname().', '.$item->getFirstname();
    }

    /**
     * @param User $item
     */
    public function getItemDescription(object $item): ?string
    {
        return null;
    }

    public function getRootItemsLoadConditions(): array
    {
        return [$this->rootItemsLoadCondition];
    }

    public function isItemToManyRelationship(): bool
    {
        return false;
    }

    public function isMissingResourcesSumVisible(): bool
    {
        return true;
    }

    public function getItemsSortMethods(): array
    {
        return [];
    }
}
