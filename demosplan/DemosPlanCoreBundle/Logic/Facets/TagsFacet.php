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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet\GroupedFacetInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagTopicResourceType;
use EDT\Querying\Contracts\FunctionInterface;

/**
 * @template-implements GroupedFacetInterface<Tag,TagTopic>
 */
class TagsFacet implements GroupedFacetInterface
{
    /**
     * @var FunctionInterface<bool>
     */
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
        return 'tags';
    }

    public function getGroupsResourceType(): string
    {
        return TagTopicResourceType::getName();
    }

    /**
     * @param TagTopic $group
     */
    public function getGroupItems(object $group): array
    {
        return $group->getTags()->getValues();
    }

    /**
     * @param Tag $item
     */
    public function getItemIdentifier(object $item): string
    {
        return $item->getId();
    }

    /**
     * @param TagTopic $group
     */
    public function getGroupIdentifier(object $group): string
    {
        return $group->getId();
    }

    /**
     * @param TagTopic $group
     */
    public function getGroupTitle(object $group): string
    {
        return $group->getTitle();
    }

    public function getGroupsLoadConditions(): array
    {
        return [];
    }

    /**
     * @param Tag $item
     */
    public function getItemTitle(object $item): string
    {
        return $item->getTitle();
    }

    /**
     * @param Tag $item
     */
    public function getItemDescription(object $item): ?string
    {
        return null;
    }

    public function getItemsResourceType(): string
    {
        return TagResourceType::getName();
    }

    public function getRootItemsLoadConditions(): array
    {
        return [$this->rootItemsLoadCondition];
    }

    public function isItemToManyRelationship(): bool
    {
        return true;
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
