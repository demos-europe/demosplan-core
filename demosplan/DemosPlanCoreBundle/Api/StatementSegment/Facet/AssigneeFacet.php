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

use demosplan\DemosPlanCoreBundle\Api\AssignableUser\AssignableUserAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;

final class AssigneeFacet implements StaticFacetInterface
{
    private const UNASSIGNED_ID = 'unassigned';

    public function __construct(
        private readonly AssignableUserAccessChecker $assignableUserAccessChecker,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(string $facet): bool
    {
        return 'assignee' === $facet;
    }

    public function getValues(Segment $segment): iterable
    {
        return null !== $segment->getAssignee() ? [$segment->getAssignee()] : [];
    }

    public function getFullOptionSet(): array
    {
        $optionSet = [];
        foreach ($this->userRepository->getEntities($this->assignableUserAccessChecker->getAccessConditions(), []) as $user) {
            $optionSet[$user->getId()] = [
                'label'      => $user->getFullname(),
                'groupId'    => null,
                'groupLabel' => null,
            ];
        }

        return $optionSet;
    }

    /**
     * Segments with no assignee are excluded from {@see getValues()}, so they need a separate
     * tally here. Mirrors the retired `segments.facets.list` RPC's `missingResourcesSum`, which
     * the frontend renders as a synthetic "not assigned" option.
     */
    public function getExtraResources(array $segments, array $selectedIds): array
    {
        $count = count(array_filter($segments, static fn (Segment $segment): bool => null === $segment->getAssignee()));

        return [Resource::create(self::UNASSIGNED_ID, '', $count, in_array(self::UNASSIGNED_ID, $selectedIds, true))];
    }
}
