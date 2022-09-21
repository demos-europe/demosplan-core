<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet;

use EDT\Querying\Contracts\FunctionInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;

/**
 * @template I of object
 * @template G of object
 *
 * @template-extends FacetInterface<I>
 */
interface GroupedFacetInterface extends FacetInterface
{
    /**
     * @return string The name of the resource type groups correspond to. Must be the name of
     *                a resource type implementing {@link ResourceTypeInterface}&lt;G&gt;.
     */
    public function getGroupsResourceType(): string;

    /**
     * @param G $group
     *
     * @return array<int,G>
     */
    public function getGroupItems(object $group): array;

    /**
     * @param G $group
     */
    public function getGroupIdentifier(object $group): string;

    /**
     * @param G $group
     */
    public function getGroupTitle(object $group): string;

    /**
     * The conditions that must match each group to be included in the result.
     *
     * All groups are conjuncted via `AND`, meaning each one limits the result further.
     * An empty array thus means no limitations beyond the
     * {@link TypeInterface::getAccessCondition() access restrictions} set in the
     * {@link GroupedFacetInterface::getGroupsResourceType() groups resource type}.
     *
     * @return array<int,FunctionInterface<bool>>
     */
    public function getGroupsLoadConditions(): array;
}
