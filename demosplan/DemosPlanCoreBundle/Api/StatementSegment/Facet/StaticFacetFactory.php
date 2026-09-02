<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

/**
 * Mirrors {@see \demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\EntityCustomFieldUsageStrategyFactory}:
 * auto-discovers all {@see StaticFacetInterface} implementations via the
 * `statement_segment_static_facet` service tag, no switch/if needed to add a new one.
 */
class StaticFacetFactory
{
    /**
     * @param iterable<StaticFacetInterface> $facets
     */
    public function __construct(
        private readonly iterable $facets,
    ) {
    }

    public function supports(string $facet): bool
    {
        return null !== $this->find($facet);
    }

    public function create(string $facet): StaticFacetInterface
    {
        $found = $this->find($facet);
        if (null === $found) {
            throw new InvalidArgumentException("No static facet strategy found for \"{$facet}\".");
        }

        return $found;
    }

    private function find(string $facet): ?StaticFacetInterface
    {
        foreach ($this->facets as $candidate) {
            if ($candidate->supports($facet)) {
                return $candidate;
            }
        }

        return null;
    }
}
