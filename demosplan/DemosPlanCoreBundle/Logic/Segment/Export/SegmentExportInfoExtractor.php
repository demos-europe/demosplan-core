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

/**
 * Describes what the segment export was asked for (search, filters, columns), for the file name and
 * the info sheet. It reads the same query parameters as the segment list: `tags.id[]`, `place.id`,
 * `assignee.id[]`, `assigneeOrUnassigned[]`, `customField[..]`, `search[value]` and, for a manual
 * selection, `id[]`.
 */
class SegmentExportInfoExtractor
{
    private const TAG_IDS_PARAM = 'tags.id';
    private const ASSIGNEE_IDS_PARAMS = ['assignee.id', 'assigneeOrUnassigned'];
    private const PLACE_IDS_PARAM = 'place.id';
    private const CUSTOM_FIELD_PARAM = 'customField';
    private const SEARCH_PARAM = 'search';
    private const SELECTED_SEGMENT_IDS_PARAM = 'id';
    private const SELECTED_COLUMNS_PARAM = 'columns';

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
        $query = $this->getQuery($this->requestStack->getCurrentRequest());
        $searchPhrase = $query[self::SEARCH_PARAM]['value'] ?? null;
        $selectedColumnKeys = array_map(
            static fn (string $key): string => self::UI_COLUMN_TO_EXPORT_KEY[$key] ?? $key,
            explode(',', (string) ($query[self::SELECTED_COLUMNS_PARAM] ?? ''))
        );
        $selectedCustomFieldIds = CustomFieldExportColumnKeyCodec::extractIds($selectedColumnKeys);

        /** @var Tag[] $tagEntities */
        $tagEntities = $this->tagRepository->findByIds($this->getIds($query, self::TAG_IDS_PARAM));
        /** @var User[] $userEntities */
        $userEntities = $this->userRepository->findBy(['id' => $this->getIds($query, ...self::ASSIGNEE_IDS_PARAMS)]);
        /** @var Place[] $placeEntities */
        $placeEntities = $this->placeRepository->findBy(['id' => $this->getIds($query, self::PLACE_IDS_PARAM)]);

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
            [] !== $this->getIds($query, self::SELECTED_SEGMENT_IDS_PARAM),
            // Without the permission the list ignores the custom field filter, so the export is not filtered by it.
            [] !== ($query[self::CUSTOM_FIELD_PARAM] ?? []) && $this->permissions->hasPermission(CustomFieldFilter::PERMISSION)
        );
    }

    private function nullIfEmpty(array $values): ?array
    {
        return [] === $values ? null : $values;
    }

    /**
     * The values of the parameters of the given names: `place.id=a` and `tags.id[]=a&tags.id[]=b`
     * both work. Empty values are left out, `assigneeOrUnassigned[]=` stands for "unassigned".
     *
     * @param array<string, mixed> $query
     *
     * @return list<string>
     */
    private function getIds(array $query, string ...$names): array
    {
        $ids = [];
        foreach ($names as $name) {
            array_push($ids, ...(array) ($query[$name] ?? []));
        }

        return array_values(array_filter($ids, static fn (mixed $id): bool => is_string($id) && '' !== $id));
    }

    /**
     * The query parameters by name, parsed from the raw query string. PHP replaces the dots in the
     * names of parameters like `tags.id` when it parses a request, so `$request->query` would only
     * know them as `tags_id`. Like API Platform does, the brackets are decoded first (browsers send
     * `tags.id%5B%5D=a`), then the names are hex-encoded while PHP parses the string.
     *
     * @return array<string, mixed>
     */
    private function getQuery(Request $request): array
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
