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

use demosplan\DemosPlanCoreBundle\Api\Tag\AccessChecker as TagAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;

/**
 * Tags are grouped by topic in the filter flyout (`segmentsFilterNames.yaml`'s `tags.grouping`),
 * hence the only facet populating `groupId`/`groupLabel`.
 */
final class TagFacet implements StaticFacetInterface
{
    public function __construct(
        private readonly TagAccessChecker $tagAccessChecker,
        private readonly TagRepository $tagRepository,
    ) {
    }

    public function supports(string $facet): bool
    {
        return 'tags' === $facet;
    }

    public function getValues(Segment $segment): iterable
    {
        return $segment->getTags();
    }

    public function getFullOptionSet(): array
    {
        $optionSet = [];
        foreach ($this->tagRepository->getEntities($this->tagAccessChecker->getAccessConditions(), []) as $tag) {
            $optionSet[$tag->getId()] = [
                'label'      => $tag->getTitle(),
                'groupId'    => $tag->getTopic()->getId(),
                'groupLabel' => $tag->getTopic()->getTitle(),
            ];
        }

        return $optionSet;
    }

    public function getExtraResources(array $segments, array $selectedIds): array
    {
        return [];
    }
}
