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

use Closure;
use demosplan\DemosPlanCoreBundle\Api\AssignableUser\AssignableUserAccessChecker;
use demosplan\DemosPlanCoreBundle\Api\Place\PlaceAccessChecker;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Facet\Resource as FacetResource;
use demosplan\DemosPlanCoreBundle\Api\Tag\AccessChecker as TagAccessChecker;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;

/**
 * The static segment-list facets (tags, assignee, place) as a lookup table: facet key => how to
 * read a segment's values, load every option, and label an option. {@see Provider} stays generic
 * and never mentions a specific facet.
 *
 * Adding a facet means adding one entry to {@see self::definitions()} (and injecting its
 * repository and access checker). Custom fields are a dynamic, per-procedure family, so they
 * live in {@see CustomFieldFacet} instead.
 */
final class StaticFacets
{
    private const UNASSIGNED_ID = 'unassigned';

    public function __construct(
        private readonly AssignableUserAccessChecker $assignableUserAccessChecker,
        private readonly PlaceAccessChecker $placeAccessChecker,
        private readonly PlaceRepository $placeRepository,
        private readonly TagAccessChecker $tagAccessChecker,
        private readonly TagRepository $tagRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function supports(string $facet): bool
    {
        return array_key_exists($facet, $this->definitions());
    }

    /**
     * The value(s) one segment has for this facet - always a list, even when there's just one
     * or none, so callers can loop the same way regardless of which facet it is.
     *
     * @return list<object>
     */
    public function getValues(string $facet, Segment $segment): array
    {
        return $this->definitions()[$facet]['values']($segment);
    }

    /**
     * Every option that exists for this facet, regardless of whether any segment currently
     * references it - so an option with zero matches still shows up with `count: 0` instead of
     * disappearing.
     *
     * @return array<string, array{label: string, groupId: ?string, groupLabel: ?string}>
     */
    public function getFullOptionSet(string $facet): array
    {
        $definition = $this->definitions()[$facet];

        $optionSet = [];
        foreach ($definition['options']() as $option) {
            [$groupId, $groupLabel] = isset($definition['group']) ? $definition['group']($option) : [null, null];

            $optionSet[$option->getId()] = [
                'label'      => $definition['label']($option),
                'groupId'    => $groupId,
                'groupLabel' => $groupLabel,
            ];
        }

        return $optionSet;
    }

    /**
     * Extra synthetic option for facets whose segments can have no value at all (assignee's
     * "unassigned"). Empty for facets that don't need one.
     *
     * @param list<Segment> $segments    every segment matching the currently active filters
     * @param list<string>  $selectedIds currently selected option ids for this facet
     *
     * @return list<FacetResource>
     */
    public function getExtraResources(string $facet, array $segments, array $selectedIds): array
    {
        $missingOptionId = $this->definitions()[$facet]['missingOptionId'] ?? null;
        if (null === $missingOptionId) {
            return [];
        }

        $count = count(array_filter(
            $segments,
            fn (Segment $segment): bool => [] === $this->getValues($facet, $segment)
        ));

        return [FacetResource::create($missingOptionId, '', $count, in_array($missingOptionId, $selectedIds, true))];
    }

    /**
     * @return array<string, array{
     *     values: Closure(Segment): list<object>,
     *     options: Closure(): iterable<object>,
     *     label: Closure(mixed): string,
     *     group?: Closure(mixed): array{0: string, 1: string},
     *     missingOptionId?: string
     * }>
     */
    private function definitions(): array
    {
        return [
            'tags' => [
                'values'  => static fn (Segment $segment): array => [...$segment->getTags()],
                'options' => fn (): iterable => $this->tagRepository->getEntities($this->tagAccessChecker->getAccessConditions(), []),
                'label'   => static fn (Tag $tag): string => $tag->getTitle(),
                'group'   => static fn (Tag $tag): array => [$tag->getTopic()->getId(), $tag->getTopic()->getTitle()],
            ],
            'place' => [
                // Segment::getPlace() is typed to return PlaceInterface (never null), but the
                // underlying `place_id` column is genuinely nullable (Segment.php:55) - the
                // type-hint doesn't reflect the DB reality here, so this null check is real.
                // @phpstan-ignore notIdentical.alwaysTrue
                'values'  => static fn (Segment $segment): array => null !== $segment->getPlace() ? [$segment->getPlace()] : [],
                'options' => fn (): iterable => $this->placeRepository->getEntities($this->placeAccessChecker->getAccessConditions(), []),
                'label'   => static fn (Place $place): string => $place->getName(),
            ],
            'assignee' => [
                'values'          => static fn (Segment $segment): array => null !== $segment->getAssignee() ? [$segment->getAssignee()] : [],
                'options'         => fn (): iterable => $this->userRepository->getEntities($this->assignableUserAccessChecker->getAccessConditions(), []),
                'label'           => static fn (User $user): string => $user->getFullname(),
                'missingOptionId' => self::UNASSIGNED_ID,
            ],
        ];
    }
}
