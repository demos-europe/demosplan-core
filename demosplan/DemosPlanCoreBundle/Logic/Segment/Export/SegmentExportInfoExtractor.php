<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\SegmentExportInfo;
use EDT\JsonApi\RequestHandling\UrlParameter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentExportInfoExtractor
{
    private const TAG_FILTER_PARAM = 'tags';
    private const ASSIGNEE_FILTER_PARAM = 'assignee';
    private const PLACE_FILTER_PARAM = 'place';
    private const SEARCH_PARAM = 'search';
    private const SELECTED_SEGMENT_IDS_PARAM = 'selectedSegments';
    private const SELECTED_COLUMNS_PARAM = 'columns';
    private const TAG_IDS_KEY = 'tagIds';
    private const ASSIGNEE_IDS_KEY = 'assigneeIds';
    private const PLACE_IDS_KEY = 'placeIds';

    /**
     * Maps column-selector keys that don't match their export column key 1:1. Anything not listed
     * here is passed through as-is.
     */
    private const UI_COLUMN_TO_EXPORT_KEY = [
        'tags' => 'tagNames',
    ];

    public function __construct(
        protected readonly PlaceRepository $placeRepository,
        protected readonly RequestStack $requestStack,
        protected readonly TagRepository $tagRepository,
        protected readonly TranslatorInterface $translator,
        protected readonly UserRepository $userRepository,
    ) {
    }

    public function extract(): SegmentExportInfo
    {
        $request = $this->requestStack->getCurrentRequest();
        $searchPhrase = $request->query->all(self::SEARCH_PARAM)['value'] ?? null;
        $selectedColumnKeys = array_map(
            static fn (string $key): string => self::UI_COLUMN_TO_EXPORT_KEY[$key] ?? $key,
            explode(',', $request->query->get(self::SELECTED_COLUMNS_PARAM))
        );
        $filter = $request->query->all(UrlParameter::FILTER);
        $isManualSelection = array_key_exists(self::SELECTED_SEGMENT_IDS_PARAM, $filter);

        $filterIds = $this->extractFilterIdsByPath($filter);
        /** @var Tag[] $tagEntities */
        $tagEntities = $this->getTags($filterIds[self::TAG_IDS_KEY]);
        /** @var User[] $userEntities */
        $userEntities = $this->getAssignees($filterIds[self::ASSIGNEE_IDS_KEY]);
        /** @var Place[] $placeEntities */
        $placeEntities = $this->getPlaces($filterIds[self::PLACE_IDS_KEY]);

        $tagNames = array_map(static fn ($tag) => $tag->getTitle(), $tagEntities);
        $assigneeNames = array_map(static fn ($user) => $user->getFullname(), $userEntities);
        $placeNames = array_map(static fn ($place) => $place->getName(), $placeEntities);

        return new SegmentExportInfo(
            $searchPhrase,
            $this->nullIfEmpty($tagNames),
            $this->nullIfEmpty($assigneeNames),
            $this->nullIfEmpty($placeNames),
            $this->nullIfEmpty($selectedColumnKeys),
            $isManualSelection
        );
    }

    private function nullIfEmpty(array $values): ?array
    {
        return [] === $values ? null : $values;
    }

    private function getTags(array $tagIds): array
    {
        return $this->tagRepository->findByIds($tagIds);
    }

    private function getAssignees(array $assigneeIds): array
    {
        return $this->userRepository->findBy(['id' => $assigneeIds]);
    }

    private function getPlaces(array $placeIds): array
    {
        return $this->placeRepository->findBy(['id' => $placeIds]);
    }

    private function extractFilterIdsByPath(array $filter): array
    {
        $filterIds = [self::TAG_IDS_KEY => [], self::ASSIGNEE_IDS_KEY => [], self::PLACE_IDS_KEY => []];
        $pathToKey = [
            self::TAG_FILTER_PARAM      => self::TAG_IDS_KEY,
            self::ASSIGNEE_FILTER_PARAM => self::ASSIGNEE_IDS_KEY,
            self::PLACE_FILTER_PARAM    => self::PLACE_IDS_KEY,
        ];

        foreach ($filter as $entry) {
            $path = $entry['condition']['path'] ?? null;
            $key = $pathToKey[$path] ?? null;

            if (null !== $key && isset($entry['condition']['value'])) {
                $filterIds[$key][] = $entry['condition']['value'];
            }
        }

        return $filterIds;
    }
}
