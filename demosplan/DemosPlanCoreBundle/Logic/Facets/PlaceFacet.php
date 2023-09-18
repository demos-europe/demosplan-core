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

use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet\FacetInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlaceResourceType;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\SortMethodInterface;

/**
 * @template-implements FacetInterface<Place>
 */
class PlaceFacet implements FacetInterface
{
    /**
     * @param FunctionInterface<bool> $rootItemsLoadCondition
     */
    public function __construct(private readonly FunctionInterface $rootItemsLoadCondition, private readonly SortMethodInterface $itemsSortMethod)
    {
    }

    public function getFacetNameTranslationKey(): string
    {
        return 'workflow.place';
    }

    public function getItemsResourceType(): string
    {
        return PlaceResourceType::getName();
    }

    public function getRootItemsLoadConditions(): array
    {
        return [$this->rootItemsLoadCondition];
    }

    /**
     * @param Place $item
     */
    public function getItemIdentifier(object $item): string
    {
        return $item->getId();
    }

    /**
     * @param Place $item
     */
    public function getItemTitle(object $item): string
    {
        return $item->getName();
    }

    /**
     * @param Place $item
     */
    public function getItemDescription(object $item): ?string
    {
        return $item->getDescription();
    }

    public function isItemToManyRelationship(): bool
    {
        return false;
    }

    public function isMissingResourcesSumVisible(): bool
    {
        return false;
    }

    public function getItemsSortMethods(): array
    {
        return [$this->itemsSortMethod];
    }
}
