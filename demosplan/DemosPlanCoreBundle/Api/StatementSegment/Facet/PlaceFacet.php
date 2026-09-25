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

use demosplan\DemosPlanCoreBundle\Api\Place\PlaceAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;

final class PlaceFacet implements StaticFacetInterface
{
    public function __construct(
        private readonly PlaceAccessChecker $placeAccessChecker,
        private readonly PlaceRepository $placeRepository,
    ) {
    }

    public function supports(string $facet): bool
    {
        return 'place' === $facet;
    }

    public function getValues(Segment $segment): iterable
    {
        // Segment::getPlace() is typed to return PlaceInterface (never null), but the
        // underlying `place_id` column is genuinely nullable (Segment.php:55) - the type-hint
        // doesn't reflect the DB reality here, so this null check is real, not dead code,
        // despite what static analysis of the declared type alone would suggest.
        // @phpstan-ignore notIdentical.alwaysTrue
        return null !== $segment->getPlace() ? [$segment->getPlace()] : [];
    }

    public function getFullOptionSet(): array
    {
        $optionSet = [];
        foreach ($this->placeRepository->getEntities($this->placeAccessChecker->getAccessConditions(), []) as $place) {
            $optionSet[$place->getId()] = [
                'label'      => $place->getName(),
                'groupId'    => null,
                'groupLabel' => null,
            ];
        }

        return $optionSet;
    }

    public function getExtraResources(array $segments, array $selectedIds): array
    {
        return [];
    }
}
