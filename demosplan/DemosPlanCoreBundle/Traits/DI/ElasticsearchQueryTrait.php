<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits\DI;

use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\AbstractQuery;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Aggregation;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Filter;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterDisplay;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterMissing;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterPrefix;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\FilterValue;
use demosplan\DemosPlanCoreBundle\Services\Elasticsearch\Sort;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Elastica\Aggregation\Missing;
use Elastica\Exception\ClientException;
use Elastica\Index;
use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\Prefix;
use Elastica\Query\QueryString;
use Elastica\Query\Terms;
use Exception;
use Pagerfanta\Elastica\ElasticaAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Traversable;

/**
 * Use this trait to gain Elasticsearch Results
 * Trait ElasticsearchQueryTrait.
 */
trait ElasticsearchQueryTrait
{
    use RequiresLoggerTrait;
    use RequiresTranslatorTrait;

    /**
     * @var Index
     */
    protected $index;

    /** @var array */
    protected $labelMaps = [];

    protected $paginatorLimits = [10, 25, 50, 100, 3000];

    /**
     * Validates that the Sort objects in $sorts are allowed (they all exist in $availableSorts).
     *
     * @param Sort[] $sorts
     * @param Sort[] $availableSorts
     */
    private function areSortsAvailable(array $sorts, array $availableSorts): bool
    {
        $getSortname = fn ($sort) => $sort->getName();

        $availableSortNames = array_map($getSortname, $availableSorts);
        $sortNames = array_map($getSortname, $sorts);

        foreach ($sortNames as $sortName) {
            if (!in_array($sortName, $availableSortNames)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Do actual Elasticsearch Query.
     *
     * @param AbstractQuery $esQuery
     * @param int           $limit   use -1 to use the default limit, use 0 if you aren't interested
     *                               in the actual entities but still want other information like
     *                               the aggregations
     * @param int           $page
     * @param Index|null    $index   if set this will be used instead of {@link index}
     */
    public function getElasticsearchResult(
        $esQuery,
        $limit = -1,
        $page = 1,
        ?Index $index = null): array
    {
        $result = [];

        try {
            $boolQuery = new BoolQuery();
            $boolQuery = $this->buildFilterMust($boolQuery, $esQuery);
            $boolQuery = $this->buildFilterShould($boolQuery, $esQuery);

            $boolQuery = $this->modifyBoolMustFilter($boolQuery, $esQuery);
            $boolQuery = $this->modifyBoolMustNotFilter($boolQuery, $esQuery);

            // if a Searchterm is set use it
            if (null !== $esQuery->getSearch() && 0 < strlen($esQuery->getSearch()->getSearchTerm())) {
                $baseQuery = new QueryString();
                $baseQuery->setQuery($esQuery->getSearch()->getSearchTerm());
                $baseQuery->setFields($esQuery->getSearch()->getFieldsArray());
                $boolQuery->addMust($baseQuery);
            }

            // generate Query
            $query = new Query();
            $query->setQuery($boolQuery);

            // generate Aggregation
            $query = $this->buildAggregation($esQuery, $query);

            // Sorting
            // default
            $esSortFields = [];
            $sorts = $esQuery->getSort();
            $availableSorts = $esQuery->getAvailableSorts();
            // Replace $esQuery->sort by $availableSorts only if $esQuery->sort doesn't already have a valid sort
            if (0 === count($sorts) || !$this->areSortsAvailable($sorts, $availableSorts)) {
                $esQuery->setSort($availableSorts);
            }
            foreach ($esQuery->getSort() as $esQuerySort) {
                foreach ($esQuerySort->getFields() as $sortField) {
                    $esSortFields[$sortField->getName()] = $sortField->getDirection();
                }
            }
            if (0 < count($esSortFields)) {
                $query->addSort($esSortFields);
            }

            $this->getLogger()->debug('Elasticsearch Procedure Query: '.DemosPlanTools::varExport($query->getQuery(), true));

            $search = $index ?? $this->index;
            $elasticaAdapter = new ElasticaAdapter($search, $query);
            $paginator = new DemosPlanPaginator($elasticaAdapter);
            $paginator->setLimits($this->paginatorLimits);

            if (-1 === $limit) {
                $limit = array_pop($this->paginatorLimits);
            } elseif (0 === $limit) {
                // Pagerfanta doesn't allow a limit of 0. Until the pagination is properly
                // refactored we use a limit of 1 as workaround.
                $limit = 1;
            }

            $paginator->setMaxPerPage((int) $limit);

            // try to paginate Result, check for validity
            try {
                $paginator->setCurrentPage($page);
            } catch (NotValidCurrentPageException $e) {
                $paginator->setCurrentPage(1);
            }

            try {
                /** @var array|Traversable $resultSet */
                $resultSet = $paginator->getCurrentPageResults();
                $result = $resultSet->getResponse()->getData();
            } catch (ClientException $e) {
                $this->logger->warning('Elasticsearch probably hit a timeout: ', [$e]);
                throw $e;
            }

            $aggregations = $resultSet->getAggregations();

            // transform Buckets info existing Filterstructure (side effect) and get aggregations (result)
            $this->generateLabelMaps($aggregations);
            $aggregation = collect($esQuery->getAvailableFilters())->mapWithKeys(function (FilterDisplay $filter) use ($aggregations) {
                $aggregationField = $filter->getAggregationField();
                $bucketContent = $aggregations[$aggregationField]['buckets'];
                $mappedValue = $this->getMappedLabel($aggregationField);
                $filterValues = $this->generateFilterArrayFromEsBucket($bucketContent, $mappedValue);
                $filter->setValues($filterValues);

                return [$filter->getName() => $filterValues];
            })->all();

            $aggregationMissing = collect($esQuery->getAvailableFilters())->mapWithKeys(function (FilterDisplay $filter) use ($aggregations) {
                $aggregationField = $filter->getName().'_missing';
                $count = $aggregations[$aggregationField]['doc_count'];

                return [$filter->getName() => $count];
            })->all();

            // add modified Aggregations to Result
            $result['aggregations'] = $aggregation;
            $result['aggregationsMissing'] = $aggregationMissing;
            $result['pager'] = $paginator;
        } catch (Exception $e) {
            $this->getLogger()->error('Elasticsearch getProcedures failed.', ['exception' => $e]);
        }

        return $result;
    }

    /**
     * Hook to modify Label Maps.
     *
     * @param array $aggregations
     */
    protected function generateLabelMaps($aggregations)
    {
    }

    protected function getMappedLabel($key)
    {
        return data_get($this->labelMaps, $key, []);
    }

    /**
     * Hook to modify Must Filters.
     *
     * @param BoolQuery     $boolQuery
     * @param AbstractQuery $esQuery
     */
    protected function modifyBoolMustFilter($boolQuery, $esQuery): BoolQuery
    {
        return $boolQuery;
    }

    /**
     * Hook to modify Must Filters.
     */
    protected function modifyBoolMustNotFilter(BoolQuery $boolQuery, AbstractQuery $esQuery): BoolQuery
    {
        return $boolQuery;
    }

    /**
     * Add param to userfilter.
     *
     * @param string $key
     * @param array  $userFilters
     * @param array  $boolMustFilter
     *
     * @return array
     */
    protected function addUserFilterTerms($key, $userFilters, $boolMustFilter)
    {
        if (isset($userFilters[$key])) {
            $value = is_array($userFilters[$key]) ? $userFilters[$key] : [$userFilters[$key]];
            $boolMustFilter[] = new Terms($key, $value);
        }

        return $boolMustFilter;
    }

    /**
     * Add param to userfilter.
     *
     * @param Filter $filter
     *
     * @return Terms
     */
    protected function getTermsQuery($filter)
    {
        $value = is_array($filter->getValue()) ? $filter->getValue() : [$filter->getValue()];

        return new Terms($filter->getField(), $value);
    }

    /**
     * Add param to userfilter.
     *
     * @param FilterMissing $filter
     *
     * @return Exists
     */
    protected function getExistsQuery($filter)
    {
        return new Exists($filter->getField());
    }

    protected function getPrefixQuery(FilterPrefix $filter): Prefix
    {
        $query = new Prefix();

        return $query->setPrefix($filter->getField(), $filter->getValue());
    }

    /**
     * @param BoolQuery     $boolQuery
     * @param AbstractQuery $esQuery
     * @param array         $boolMustFilters
     * @param array         $boolMustNotFilters
     */
    public function buildFilterMust($boolQuery, $esQuery, $boolMustFilters = [], $boolMustNotFilters = []): BoolQuery
    {
        foreach ($esQuery->getFiltersMust() as $filter) {
            if ($filter instanceof FilterMissing) {
                $boolMustNotFilters[] = $this->getExistsQuery($filter);
            }
            if ($filter instanceof Filter) {
                $boolMustFilters[] = $this->getTermsQuery($filter);
            }
            if ($filter instanceof FilterPrefix) {
                $boolMustFilters[] = $this->getPrefixQuery($filter);
            }
        }
        if (0 < count($boolMustFilters)) {
            array_map($boolQuery->addMust(...), $boolMustFilters);
        }
        if (0 < count($boolMustNotFilters)) {
            array_map($boolQuery->addMustNot(...), $boolMustNotFilters);
        }
        if (0 < count($esQuery->getFilterMustNotQueries())) {
            array_map($boolQuery->addMustNot(...), $esQuery->getFilterMustNotQueries());
        }
        if (0 < count($esQuery->getFilterMustQueries())) {
            array_map($boolQuery->addMust(...), $esQuery->getFilterMustQueries());
        }

        return $boolQuery;
    }

    /**
     * @param BoolQuery     $boolQuery
     * @param AbstractQuery $esQuery
     * @param array         $boolShouldFilter
     *
     * @return BoolQuery
     */
    public function buildFilterShould($boolQuery, $esQuery, $boolShouldFilter = [])
    {
        foreach ($esQuery->getFiltersShould() as $filter) {
            if ($filter instanceof FilterMissing) {
                $boolShouldFilter[] = $this->getExistsQuery($filter);
            }
            if ($filter instanceof Filter) {
                $boolShouldFilter[] = $this->getTermsQuery($filter);
            }
        }

        if (0 < count($boolShouldFilter)) {
            array_map($boolQuery->addShould(...), $boolShouldFilter);
            $boolQuery = $this->setMinimumShouldMatch($boolQuery, 1);
        }

        return $boolQuery;
    }

    /**
     * @param BoolQuery $boolQuery
     * @param int       $minimumShouldMatch
     */
    protected function setMinimumShouldMatch($boolQuery, $minimumShouldMatch): BoolQuery
    {
        return $boolQuery->setMinimumShouldMatch($minimumShouldMatch);
    }

    /**
     * @param AbstractQuery $esQuery
     * @param Query         $query
     *
     * @return Query
     */
    public function buildAggregation($esQuery, $query)
    {
        foreach ($esQuery->getAvailableFilters() as $availableFilter) {
            $query = $this->addMissingAggregation($query, $availableFilter);
            $query = $this->addTermsAggregation($query, $availableFilter);
        }

        return $query;
    }

    /**
     * @param string      $key
     * @param string|null $orderBy
     * @param string|null $orderDir asc|desc
     *
     * @return Query
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/guide/1.x/_intrinsic_sorts.html
     */
    protected function addEsAggregation(Query $query, $key, $orderBy = null, $orderDir = null)
    {
        $aggPriority = new \Elastica\Aggregation\Terms($key);
        $aggPriority->setField($key);
        // we usually need all Aggregations, 0 is not allowed any more. Use some reasonably big magic number
        $aggPriority->setSize(10000);
        if (!is_null($orderBy)) {
            $aggPriority->setOrder($orderBy, $orderDir);
        }
        $query->addAggregation($aggPriority);

        return $query;
    }

    /**
     * @return Query
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/guide/1.x/_intrinsic_sorts.html
     */
    protected function addTermsAggregation(Query $query, FilterDisplay $filterDisplay)
    {
        $aggPriority = new \Elastica\Aggregation\Terms($filterDisplay->getAggregationField());
        $aggPriority->setField($filterDisplay->getAggregationField());
        // we usually need all Aggregations, 0 is not allowed any more. Use some reasonably big magic number
        $aggPriority->setSize(10000);
        $query->addAggregation($aggPriority);

        return $query;
    }

    /**
     * @return Query
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/guide/1.x/_intrinsic_sorts.html
     */
    protected function addMissingAggregation(Query $query, FilterDisplay $filterDisplay)
    {
        $aggPriority = new Missing($filterDisplay->getName().'_missing', $filterDisplay->getName());
        $aggPriority->setField($filterDisplay->getAggregationField());
        $query->addAggregation($aggPriority);

        return $query;
    }

    /**
     * Transform Elasticsearch Buckets info existing Filterstructure.
     *
     * @param array  $bucket
     * @param array  $labelMap array with labels for bucket keys
     * @param string $labelKey Key used to get the label from each entry in the given bucket. Defaults to 'key'.
     * @param string $valueKey Key used to get the value from each entry in the given bucket. Defaults to 'key'.
     * @param string $countKey Key used to get the count from each entry in the given bucket. Defaults to 'doc_count'.
     *
     * @return array
     */
    protected function generateFilterArrayFromEsBucket($bucket, $labelMap = [], $labelKey = 'key', $valueKey = 'key', $countKey = 'doc_count')
    {
        $filter = [];
        if ((!is_array($bucket) || 0 === count($bucket)) && 0 === count($labelMap)) {
            return $filter;
        }

        foreach ($bucket as $entry) {
            $filterEntry = [
                'count' => $entry[$countKey],
                'label' => array_key_exists($entry[$labelKey], $labelMap) ? $labelMap[$entry[$labelKey]] : $entry[$labelKey],
                'value' => $entry[$valueKey],
            ];
            // Setze einen Stadardwert, wenn kein Label angegeben ist
            if ('' === $filterEntry['label']) {
                $filterEntry['label'] = 'Keine Zuordnung';
            }
            $filter[] = $filterEntry;
        }

        // sortiere nach Label
        // @todo Sortierung nach 7, 7.1, 7.1.1 funktioniert noch nicht
        usort(
            $filter,
            function ($a, $b) {
                // Missing has to be on top
                if (0 === strcmp((string) $a['label'], 'Keine Zuordnung')) {
                    return -1;
                }
                if (0 === strcmp((string) $b['label'], 'Keine Zuordnung')) {
                    return 1;
                }

                return strnatcasecmp((string) $a['label'], (string) $b['label']);
            }
        );

        return $filter;
    }

    protected function generateFilterArrayFromEsMissing($bucket)
    {
        return [
            'count' => $bucket['doc_count'],
            'label' => 'Keine Zuordnung',
            'value' => null,
        ];
    }

    /**
     * Convert Result to Legacy.
     *
     * @param array $elasticsearchResult
     */
    public function toLegacyResultES($elasticsearchResult): array
    {
        $list = [];
        // avoid undefined index
        if (!isset($elasticsearchResult['hits']) || !isset($elasticsearchResult['hits']['hits'])) {
            $elasticsearchResult['hits']['hits'] = [];
            $elasticsearchResult['hits']['total'] = 0;
        }
        foreach ($elasticsearchResult['hits']['hits'] as $hit) {
            $list[] = $this->convertElasticsearchHitToLegacy($hit);
        }

        return $list;
    }

    /**
     * Konvertiere das Ergebnis aus Elasticsearch zu Legacy.
     *
     * @param array $hit
     */
    protected function convertElasticsearchHitToLegacy($hit)
    {
        $singleHit = $hit['_source'];
        $singleHit['ident'] = $hit['_id'];
        $singleHit['id'] = $hit['_id'];
        if (array_key_exists('coordinate', $singleHit)) {
            $singleHit['settings']['coordinate'] = $singleHit['coordinate'];
        }
        if (array_key_exists('publicParticipationStartDateTimestamp', $singleHit)) {
            $singleHit['publicParticipationStartDate'] = $singleHit['publicParticipationStartDateTimestamp'];
        }
        if (array_key_exists('publicParticipationEndDateTimestamp', $singleHit)) {
            $singleHit['publicParticipationEndDate'] = $singleHit['publicParticipationEndDateTimestamp'];
        }

        return $singleHit;
    }

    /**
     * @param AbstractQuery $esQuery
     * @param array         $aggregationsFilterResult
     * @param array         $labelMaps
     */
    public function prepareEsQueryDisplayFilters($esQuery, $aggregationsFilterResult, $labelMaps = [])
    {
        $filters = $esQuery->getAvailableFilters();
        foreach ($filters as $filter) {
            $labelMap = array_key_exists($filter->getName(), $labelMaps) ? $labelMaps[$filter->getName()] : [];
            $filterValues = $this->generateFilterValues($aggregationsFilterResult[$filter->getName()], $labelMap);
            $filterValues = collect($filterValues)->sortBy(fn ($filterValue) =>
                /* @var FilterValue $filterValue */
                $filterValue->getLabel())->toArray();
            if ($filter->hasNoAssignmentSelectOption()) {
                $filterValues[] = $this->generateMissingFilterValue($filter, $aggregationsFilterResult);
            }
            $filter->setValues($filterValues);
        }
    }

    /**
     * Gets buckets and generates possible FilterValues.
     * Those are put into the FilterDisplay and are rendered
     * later on in the frontend.
     *
     * @param array $aggregationFilterResult
     * @param array $labelMap
     *
     * @return FilterValue[]
     */
    public function generateFilterValues($aggregationFilterResult, $labelMap = [])
    {
        $filterValues = [];
        foreach ($aggregationFilterResult['buckets'] as $bucket) {
            $label = array_key_exists($bucket['key'], $labelMap) ? $labelMap[$bucket['key']] : $bucket['key'];
            /* @var FilterValue $filterValue */
            $filterValues[] = $this->generateFilterValue($bucket, $label);
        }

        return $filterValues;
    }

    /**
     * @param array  $bucket
     * @param string $label
     */
    public function generateFilterValue($bucket, $label): FilterValue
    {
        $filterValue = new FilterValue($bucket['key']);
        $filterValue->setLabel($label);
        $filterValue->setAggregation($this->generateAggregationFromBucket($bucket));

        return $filterValue;
    }

    /**
     * @param array $bucket
     *
     * @return Aggregation
     */
    public function generateAggregationFromBucket($bucket)
    {
        $aggregation = new Aggregation($bucket['key']);
        $aggregation->setAmount($bucket['doc_count']);
        $aggregation->setName($bucket['key']);

        return $aggregation;
    }

    /**
     * @param FilterDisplay $filter
     * @param array         $aggregations
     *
     * @return FilterValue
     */
    public function generateMissingFilterValue($filter, $aggregations)
    {
        $keyInAggregations = $filter->getName().'_missing';
        $bucket = [
            'key'       => '',
            'doc_count' => 0,
        ];
        if (array_key_exists($keyInAggregations, $aggregations)) {
            $bucket = array_merge($bucket, $aggregations[$keyInAggregations]);
        }
        $noAssignment = $this->translator->trans('filter.noAssignment');

        return $this->generateFilterValue($bucket, $noAssignment);
    }

    public function getIndex(): Index
    {
        return $this->index;
    }
}
