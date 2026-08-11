<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\StoredQuery;

use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;

class SegmentListQuery extends AbstractStoredQuery
{
    private const FILTER = 'filter';
    private const PROCEDURE_ID = 'procedureId';
    private const SEARCH_PHRASE = 'searchPhrase';
    private const VIEW_SETTINGS = 'viewSettings';
    private const COLUMN_ORDER = 'columnOrder';
    private const SELECTED_COLUMNS = 'selectedColumns';
    private const SORTING = 'sorting';

    /**
     * @var array The stored filter query
     */
    protected $filter = [];

    /**
     * @var string
     */
    protected $procedureId;

    /**
     * @var string|null
     */
    protected $searchPhrase;

    /**
     * @var array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string}
     */
    protected $viewSettings = [];

    public function getFormat(): string
    {
        return 'segment_list';
    }

    public function fromJson(array $json): void
    {
        $this->filter = $json[self::FILTER];
        $this->procedureId = $json[self::PROCEDURE_ID];
        // searchPhrase was introduced later, hence we need to expect JSON in the database without this key
        $this->searchPhrase = $json[self::SEARCH_PHRASE] ?? null;
        // viewSettings was introduced later, hence we need to expect JSON in the database without this key
        $this->viewSettings = $this->normalizeViewSettings($json[self::VIEW_SETTINGS] ?? []);
    }

    public function toJson(): array
    {
        $json = [
            self::FILTER        => $this->filter,
            self::PROCEDURE_ID  => $this->procedureId,
            self::SEARCH_PHRASE => $this->searchPhrase,
        ];

        /*
         * Emitted only when there is something to emit, so a query without view settings serializes
         * byte-identically to how it did before this key existed and therefore keeps its hash.
         */
        if ([] !== $this->viewSettings) {
            $json[self::VIEW_SETTINGS] = $this->viewSettings;
        }

        return $json;
    }

    /**
     * The filter format used in the JSON:API implementation as an associative array.
     *
     * @see DrupalFilterParser
     */
    public function getFilter(): array
    {
        return $this->filter;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function setFilter(array $filter): void
    {
        $this->filter = $filter;
    }

    public function setProcedureId(string $procedureId): void
    {
        $this->procedureId = $procedureId;
    }

    public function getSearchPhrase(): ?string
    {
        return $this->searchPhrase;
    }

    /**
     * A search string to use for fuzzy matching. Which field will be accessed for the fuzzy
     * matching is decided by the backend configuration. Will be ignored if `null`.
     */
    public function setSearchPhrase(?string $searchPhrase): void
    {
        $this->searchPhrase = $searchPhrase;
    }

    /**
     * How the segment list is presented: the visible columns, their order, and the sorting. Always
     * normalized, see {@see normalizeViewSettings()}.
     *
     * @return array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string}
     */
    public function getViewSettings(): array
    {
        return $this->viewSettings;
    }

    /**
     * Normalizes on the way in, so that callers cannot accidentally produce two different hashes for
     * the same view. Rejecting a malformed request payload is a separate concern and stays at the
     * request edge.
     *
     * Every key is optional:
     *
     *     [
     *         'selectedColumns' => ['recommendation', 'tags', 'text'],     // visible columns, a set
     *         'columnOrder'     => ['externId', 'text', 'recommendation'], // left to right, a sequence
     *         'sorting'         => '-deadline',                            // `-` prefix for descending
     *     ]
     *
     * `columnOrder` may contain keys absent from `selectedColumns`, since `externId` is always
     * displayed and therefore never selectable. Passing `[]` clears the settings.
     *
     * @param array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string|null} $viewSettings
     */
    public function setViewSettings(array $viewSettings): void
    {
        $this->viewSettings = $this->normalizeViewSettings($viewSettings);
    }

    /**
     * The enclosing query is addressed by a digest of its JSON, so two clients describing the same
     * view have to serialize identically:
     *
     * - keys are emitted in a fixed order,
     * - empty values are dropped rather than kept as `[]` or `null`, which is what lets
     *   {@see toJson()} omit the whole key and leave pre-existing hashes untouched,
     * - `selectedColumns` is sorted, because display order comes from the table configuration rather
     *   than from this list, making the digest insensitive to the order the user clicked,
     * - `columnOrder` keeps its order, because that order *is* the payload.
     *
     * Applied on read as well as on write, so rows written before this key existed - or by an older
     * version - decode into the same shape.
     *
     * @param array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string|null} $viewSettings
     *
     * @return array{selectedColumns?: list<string>, columnOrder?: list<string>, sorting?: string}
     */
    private function normalizeViewSettings(array $viewSettings): array
    {
        $selectedColumns = $this->normalizeColumnList($viewSettings[self::SELECTED_COLUMNS] ?? []);
        sort($selectedColumns);

        $columnOrder = $this->normalizeColumnList($viewSettings[self::COLUMN_ORDER] ?? []);
        $sorting = $this->normalizeSorting($viewSettings[self::SORTING] ?? null);

        $normalized = [];

        if ([] !== $selectedColumns) {
            $normalized[self::SELECTED_COLUMNS] = $selectedColumns;
        }

        if ([] !== $columnOrder) {
            $normalized[self::COLUMN_ORDER] = $columnOrder;
        }

        if (null !== $sorting) {
            $normalized[self::SORTING] = $sorting;
        }

        return $normalized;
    }

    /**
     * Column keys are dynamic - static segment fields alongside `customField_<uuid>` entries - so only
     * the shape is enforced here: a list of non-empty strings, deduplicated.
     *
     * Values are not trimmed on purpose: `" text "` is not a whitespace variant of the column `text`,
     * it is an invalid key either way, and quietly repairing it would hide a frontend bug.
     *
     * @return list<string>
     */
    private function normalizeColumnList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $columns = array_filter($value, static fn (mixed $column): bool => is_string($column) && '' !== $column);

        return array_values(array_unique($columns));
    }

    /**
     * A sort expression in the format the segment list uses, for example `deadline`, or `-deadline`
     * for descending. Not validated against known fields, because the selectable columns include
     * dynamically configured custom fields.
     *
     * A single expression has no internal order, so nothing has to be canonicalized here. **If this
     * ever becomes multi-column** - say a `{name: desc, id: asc}` map - its key order would start
     * moving the hash, and it would need sorting the way `selectedColumns` is.
     */
    private function normalizeSorting(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $sorting = trim($value);

        return '' === $sorting ? null : $sorting;
    }
}
