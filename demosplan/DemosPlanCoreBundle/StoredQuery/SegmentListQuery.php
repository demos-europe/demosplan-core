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
    }

    public function toJson(): array
    {
        return [
            self::FILTER        => $this->filter,
            self::PROCEDURE_ID  => $this->procedureId,
            self::SEARCH_PHRASE => $this->searchPhrase,
        ];
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
}
