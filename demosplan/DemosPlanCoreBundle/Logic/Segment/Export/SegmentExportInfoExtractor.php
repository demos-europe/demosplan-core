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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Api\StatementSegment\Filter\CustomFieldFilter;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\TagRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldExportColumnKeyCodec;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\SegmentExportInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentExportInfoExtractor
{
    private const TAG_FILTER_PARAM = 'tags.id';
    private const ASSIGNEE_FILTER_PARAM = 'assignee.id';
    private const ASSIGNEE_OR_UNASSIGNED_FILTER_PARAM = 'assigneeOrUnassigned';
    private const PLACE_FILTER_PARAM = 'place.id';
    private const CUSTOM_FIELD_FILTER_PARAM = 'customField';
    private const SEARCH_PARAM = 'search';
    private const SELECTED_SEGMENT_IDS_PARAM = 'id';
    private const SELECTED_COLUMNS_PARAM = 'columns';
    private const TAG_IDS_KEY = 'tagIds';
    private const ASSIGNEE_IDS_KEY = 'assigneeIds';
    private const PLACE_IDS_KEY = 'placeIds';

    /**
     * Maps column-selector keys that don't match their export column key 1:1. Anything not listed
     * here is passed through as-is.
     */
    private const UI_COLUMN_TO_EXPORT_KEY = [
        'tags'         => 'tagNames',
        'organisation' => 'oName',
    ];

    public function __construct(
        protected readonly PermissionsInterface $permissions,
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
        $selectedCustomFieldIds = CustomFieldExportColumnKeyCodec::extractIds($selectedColumnKeys);
        $filter = $this->getFilterParameters($request);
        $isManualSelection = $request->query->has(self::SELECTED_SEGMENT_IDS_PARAM);

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
            $this->nullIfEmpty($selectedCustomFieldIds),
            $isManualSelection,
            $this->hasCustomFieldFilter($request)
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
        return [
            self::TAG_IDS_KEY      => $this->getIds($filter, self::TAG_FILTER_PARAM),
            self::ASSIGNEE_IDS_KEY => $this->getIds($filter, self::ASSIGNEE_FILTER_PARAM, self::ASSIGNEE_OR_UNASSIGNED_FILTER_PARAM),
            self::PLACE_IDS_KEY    => $this->getIds($filter, self::PLACE_FILTER_PARAM),
        ];
    }

    /**
     * Empty values are left out, `assigneeOrUnassigned[]=` stands for "unassigned".
     *
     * @return list<string>
     */
    private function getIds(array $filter, string ...$parameters): array
    {
        return collect($parameters)
            ->flatMap(static fn (string $parameter): array => (array) ($filter[$parameter] ?? []))
            ->filter(static fn (mixed $id): bool => is_string($id) && '' !== $id)
            ->values()
            ->all();
    }

    /**
     * Without the permission the list ignores the custom field filter, so the export is not filtered by it.
     */
    private function hasCustomFieldFilter(Request $request): bool
    {
        return $request->query->has(self::CUSTOM_FIELD_FILTER_PARAM)
            && $this->permissions->hasPermission(CustomFieldFilter::PERMISSION);
    }

    /**
     * The filter parameters of the segment list by name. PHP replaces the dots in the names of
     * parameters like `tags.id` when it parses a request, so `$request->query` would only know them
     * as `tags_id`. Like API Platform does, the brackets are decoded first (browsers send
     * `tags.id%5B%5D=a`), then the names are hex-encoded while PHP parses the raw query string.
     */
    private function getFilterParameters(Request $request): array
    {
        $encoded = preg_replace_callback(
            '/(^|(?<=&))[^=[&]+/',
            static fn (array $name): string => bin2hex(urldecode($name[0])),
            str_ireplace('%5B', '[', (string) $request->server->get('QUERY_STRING', ''))
        );
        parse_str($encoded, $parameters);

        return array_combine(array_map(static fn (int|string $name): string => hex2bin((string) $name), array_keys($parameters)), $parameters);
    }
}
