<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResult;
use demosplan\DemosPlanCoreBundle\ValueObject\ElasticsearchResultSet;
use Elastica\Aggregation\Missing;
use Elastica\Aggregation\Nested;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\Exists;
use Elastica\Query\QueryString;
use Elastica\Query\Terms;
use Exception;

use function array_key_exists;

class ElasticSearchService extends CoreService
{
    final public const EXISTING_FIELD_FILTER = '*';
    final public const KEINE_ZUORDNUNG = 'keinezuordnung';
    final public const EMPTY_FIELD = 'no_value';

    /**
     * Number of Documents to be counted as an Elasticsearch aggregation.
     *
     * @var int
     */
    protected $aggregationsMinDocumentCount = 1;

    public function __construct(private readonly EditorService $editorService, private readonly ElasticsearchFilterArrayTransformer $elasticsearchFilterArrayTransformer, private readonly PermissionsInterface $permissions, private readonly UserService $userService)
    {
    }

    /**
     * Creates a Missing aggregation for elasticsearch. This is needed if we want
     * to lookup something from the index that does NOT have a relation to another
     * datapoint. In SQL-speak this would be similar to a left join which we use to
     * also collect all elements of one table that have no relation to another table.
     *
     * @param string $key
     */
    public function addEsMissingAggregation(Query $query, $key): Query
    {
        $aggPriority = new Missing(
            $key.'_missing', $key
        );
        $aggPriority->setField($key);
        $query->addAggregation($aggPriority);

        return $query;
    }

    /**
     * @param string      $key
     * @param string|null $orderBy
     * @param string|null $orderDir  asc|desc
     * @param string|null $bucketKey
     *
     * @see https://www.elastic.co/guide/en/elasticsearch/guide/1.x/_intrinsic_sorts.html
     */
    public function addEsAggregation(Query $query, $key, $orderBy = null, $orderDir = null, $bucketKey = null): Query
    {
        $bucketKey ??= $key;
        $aggPriority = new \Elastica\Aggregation\Terms($bucketKey);
        $aggPriority->setField($key);
        // we usually need all Aggregations, 0 is not allowed any more. Use some reasonably big magic number
        $aggPriority->setSize(10000);
        if (null !== $orderBy) {
            $aggPriority->setOrder($orderBy, $orderDir);
        }
        /*
         * Beware:
         * Setting min_doc_count=0 will also return buckets for terms that
         * didn’t match any hit. However, some of the returned terms which have
         * a document count of zero might only belong to **deleted documents or
         * documents from other types (!!!)**, so there is no warranty that a
         * match_all query would find a positive document count for those terms.
         * https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html
         */
        $aggPriority->setMinimumDocumentCount($this->aggregationsMinDocumentCount);
        $query->addAggregation($aggPriority);

        return $query;
    }

    public function setAggregationsMinDocumentCount($aggregationsMinDocumentCount): void
    {
        $this->aggregationsMinDocumentCount = $aggregationsMinDocumentCount;
    }

    /**
     * Set emtpy return result.
     */
    public function getESEmptyResult(string $userWarningTransKey = null): ElasticsearchResult
    {
        $elasticsearchResultStatement = new ElasticsearchResult();
        $elasticsearchResultStatement->setAggregations([]);
        $elasticsearchResultStatement->setHits(['hits' => [], 'total' => 0]);
        $elasticsearchResultStatement->setUserWarning($userWarningTransKey);

        return $elasticsearchResultStatement;
    }

    /**
     * @param string $keyInAggregation
     * @param array  $fromArray
     * @param array  $aggregation
     * @param array  $labelMap
     * @param string $labelKey
     * @param string $valueKey
     * @param string $countKey
     */
    protected function addAggregationResultToArrayFromArray($keyInAggregation, $fromArray, $aggregation, $labelMap = [], $labelKey = 'key', $valueKey = 'key', $countKey = 'doc_count')
    {
        $generatedFilterArray = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsBucket(
            $fromArray,
            $labelMap,
            $labelKey,
            $valueKey,
            $countKey
        );
        $aggregation[$keyInAggregation] = isset($aggregation[$keyInAggregation])
            ? \array_merge(
                $aggregation[$keyInAggregation],
                $generatedFilterArray
            )
            : $generatedFilterArray;

        return $aggregation;
    }

    /**
     * Adds the result of an aggregation to the result array, which is passed to frontend.
     * I just kept names as they where before, to not confuse more than it already is.
     *
     * @param string $keyInAggregations
     * @param string $keyInAggregation
     * @param array  $aggregations
     * @param array  $aggregation
     * @param array  $labelMap
     *
     * @return array
     */
    public function addAggregationResultToArray($keyInAggregations, $keyInAggregation, $aggregations, $aggregation, $labelMap = [])
    {
        if (isset($aggregations[$keyInAggregations])) {
            return $this->addAggregationResultToArrayFromArray(
                $keyInAggregation,
                $aggregations[$keyInAggregations]['buckets'],
                $aggregation,
                $labelMap
            );
        }

        return $aggregation;
    }

    /**
     * This method adds the result of an aggregation to the array, that is passed to FE
     * To not confuse (it's already confusing enough) i just copied the naming of variables.
     *
     * @param string $nameInAggregations
     * @param string $keyInAggregation
     * @param array  $aggregations
     * @param array  $aggregation
     *
     * @return array
     */
    public function addMissingAggregationResultToArray($nameInAggregations, $keyInAggregation, $aggregations, $aggregation)
    {
        if (isset($aggregations[$nameInAggregations.'_missing'])) {
            $aggregation[$keyInAggregation][] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsMissing(
                $aggregations[$nameInAggregations.'_missing']
            );
        } else {
            $aggregation[$keyInAggregation] = [];
        }

        return $aggregation;
    }

    /**
     * This method adds the result of an fragment-aggregation to the array, that is passed to FE
     * To not confuse (it's already confusing enough) i just copied the naming of variables.
     *
     * @param string $nameInAggregations
     * @param string $keyInAggregation
     * @param array  $aggregations
     * @param array  $aggregation
     *
     * @return array
     */
    public function addFragmentsMissingAggregationResultToArray($nameInAggregations, $keyInAggregation, $aggregations, $aggregation)
    {
        if (isset($aggregations[$nameInAggregations.'_missing'])) {
            if (!isset($aggregation[$keyInAggregation])) {
                $aggregation[$keyInAggregation] = [];
            }
            $aggregation[$keyInAggregation][] = $this->elasticsearchFilterArrayTransformer->generateFilterArrayFromEsFragmentsMissing(
                $aggregations[$nameInAggregations.'_missing'],
                $nameInAggregations.'_missing'
            );
        }

        return $aggregation;
    }

    /**
     * Transform Elasticsearch Buckets info existing Filterstructure and transform userId to Username.
     *
     * @param array  $bucket
     * @param string $labelKey Key used to get the label from each entry in the given bucket. Defaults to 'key'.
     * @param string $valueKey Key used to get the value from each entry in the given bucket. Defaults to 'key'.
     * @param string $countKey Key used to get the count from each entry in the given bucket. Defaults to 'doc_count'.
     *
     * @return array
     *
     * @throws Exception
     */
    public function generateFilterArrayFromUserAssignEsBucket($bucket, $labelKey = 'key', $valueKey = 'key', $countKey = 'doc_count')
    {
        foreach ($bucket as $key => $entry) {
            $user = $this->userService->getSingleUser($entry[$valueKey]);
            $bucket[$key] = [
                'label' => $user instanceof User ? $user->getName().' -- '.$user->getOrgaName() : $entry[$labelKey],
                'value' => $entry[$valueKey],
                'count' => $entry[$countKey],
            ];
        }
        // sort by Label
        \usort(
            $bucket,
            fn ($a, $b) => \strnatcasecmp((string) $a['label'], (string) $b['label'])
        );

        return $bucket;
    }

    /**
     * @param string $keyInFragmentEsResult
     * @param string $keyInAggregation
     * @param array  $fragmentAggregations
     * @param array  $aggregation
     * @param array  $labelMap
     */
    public function addFragmentEsResultToArray($keyInFragmentEsResult, $keyInAggregation, $fragmentAggregations, $aggregation, $labelMap = [])
    {
        if (isset($fragmentAggregations[$keyInFragmentEsResult])) {
            $aggregation = $this->addAggregationResultToArrayFromArray(
                $keyInAggregation,
                $fragmentAggregations[$keyInFragmentEsResult],
                $aggregation,
                $labelMap,
                'value',
                'value',
                'count'
            );
        }

        return $aggregation;
    }

    /**
     * @param BoolQuery $boolQuery
     * @param int       $minimumShouldMatch
     */
    public function setMinimumShouldMatch($boolQuery, $minimumShouldMatch): BoolQuery
    {
        return $boolQuery->setMinimumShouldMatch($minimumShouldMatch);
    }

    /**
     * Creates a basic SearchQuery for Elasticsearch queries.
     *
     * @param array<int,string|array<int,string>> $searchFields
     */
    public function createSearchQuery(string $search, array $searchFields): AbstractQuery
    {
        $baseQuery = new QueryString();
        // colons and slashes must be masked to avoid shard failures
        $search = \str_replace([':', '/'], ['\:', '\/'], \trim($search));

        // when searching for integers we possibly search for externIds and
        // should also find manual Statements and Cluster
        if (\is_numeric($search)) {
            $search = '('.$search.' OR M'.$search.' OR GM'.$search.' OR G'.$search.')';
        }

        // when searching for a manual statement or group but using wrong case
        // fix it silently
        if (1 === \preg_match('/^([gm]+\d+)/i', $search, $matches)) {
            $search = \strtoupper($matches[1]);
        }

        // search without wildcards, to receive Results for exact search Terms
        // like GM1446 in externId, which is not found with *$search*
        $baseQuery->setQuery($search);

        // a search field may contain multiple Elasticsearch fields like a search in
        // element, paragraph and document title. Search in any of those fields
        $usedSearchFields = [];
        foreach ($searchFields as $esField) {
            if (is_array($esField)) {
                $usedSearchFields = [...$usedSearchFields, ...$esField];
            } else {
                $usedSearchFields[] = $esField;
            }
        }

        $baseQuery->setFields($usedSearchFields);

        return $baseQuery;
    }

    /**
     * Calculate Missing Aggregation for Fragments.
     *
     * This is a bit trickier, as missing should only count if statement has fragment at all
     *
     * @param string $key
     */
    public function addEsFragmentsMissingAggregation($key, Query $query): Query
    {
        $aggPriority = new Missing(
            $key.'_missing', $key
        );
        $aggPriority->setField($key);

        // Count statements found in missing aggregation
        $statementCount = new \Elastica\Aggregation\Terms('statements');
        $statementCount->setField('fragments.statementId');
        // we could get more than top 10 aggregations with $statementCount->setSize(0);
        // but we also could do some math in aggregation evaluation later on as
        // we do only need the amount, not the ids
        $aggPriority->addAggregation($statementCount);

        $nested = new Nested(
            $key.'_missing',
            'fragments'
        );
        $nested->addAggregation($aggPriority);

        $query->addAggregation($nested);

        return $query;
    }

    /**
     * Returns the Terms Filter according to Elasticsearch version.
     *
     * @param string $field
     * @param array  $terms
     *
     * @return Terms
     *
     * @throws Exception
     */
    public function getElasticaTermsInstance($field, $terms)
    {
        // terms needs to be an array
        $terms = \is_array($terms) ? $terms : [$terms];

        return new Terms($field, $terms);
    }

    /**
     * Returns the Missing Filter according to Elasticsearch version.
     *
     * @param string $field
     *
     * @throws Exception
     */
    public function getElasticaExistsInstance($field): Exists
    {
        return new Exists($field);
    }

    /**
     * Add param to userfilter.
     *
     * @param string $key
     * @param array  $userFilters
     * @param array  $boolMustFilter
     * @param array  $rawFields
     * @param bool   $addAllAggregations - If true, will add all filters existing on $userFilters. Otherwise only those who also has a not empty value.
     *
     * @return array Elastica Filter
     *
     * @throws Exception
     */
    public function addUserFilter($key, $userFilters, $boolMustFilter, $boolMustNotFilter, $nullvalue = null, $rawFields = [], $addAllAggregations = true)
    {
        if (array_key_exists($key, $userFilters)
            && ($addAllAggregations || $this->hasFilterValue($userFilters[$key]))
        ) {
            $value = \is_array($userFilters[$key]) ? $userFilters[$key] : [$userFilters[$key]];
            $key = \in_array($key, $rawFields, true) ? $key.'.raw' : $key;
            $count = count($value);
            for ($i = 0; $i < $count; ++$i) {
                if ($value[$i] === $nullvalue || self::KEINE_ZUORDNUNG === $value[$i]) {
                    $boolMustNotFilter[] = $this->getElasticaExistsInstance(
                        $key
                    );
                } elseif (self::EXISTING_FIELD_FILTER === $value[$i]) {
                    $boolMustFilter[] = $this->getElasticaExistsInstance(
                        $key
                    );
                } else {
                    // replace empty field placeholder
                    if (self::EMPTY_FIELD === $value[$i]) {
                        $value[$i] = '';
                    }

                    $boolMustFilter[] = $this->getElasticaTermsInstance(
                        $key,
                        $value[$i]
                    );
                }
            }
        }

        return [$boolMustFilter, $boolMustNotFilter];
    }

    /**
     * Given a $filter (can be array or string) returns true if has no empty value and false otherwise.
     *
     * @return bool
     */
    private function hasFilterValue($filter)
    {
        if (is_array($filter)) {
            foreach ($filter as $filterValue) {
                if ('' !== $filterValue) {
                    return true;
                }
            }
        } else {
            if ('' !== $filter) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert Result to Legacy.
     *
     * @param string|null $search
     * @param array       $filters
     * @param array       $sort
     * @param string      $resultKey
     */
    public function simplifyEsStructure(
        ElasticsearchResult $elasticsearchResult,
        $search = '',
        $filters = [],
        $sort = null,
        $resultKey = 'statements'
    ): ElasticsearchResultSet {
        $filterSet = [
            'total'   => is_countable($elasticsearchResult->getAggregations()) ? count($elasticsearchResult->getAggregations()) : 0,
            'offset'  => 0,
            'limit'   => 0,
            'filters' => $elasticsearchResult->getAggregations(),
        ];
        if (null !== $elasticsearchResult->getUserWarning()) {
            $filterSet['userWarning'] = $elasticsearchResult->getUserWarning();
        }
        if (null !== $filters) {
            foreach ($filters as $filterKey => $filterValue) {
                $filterValue = is_array($filterValue) ? array_unique($filterValue) : $filterValue;
                // Wenn ein Filter in der Aggregation gefunden wurde, ist er via Interface ausgewählt und aktiv
                if (array_key_exists($filterKey, $elasticsearchResult->getAggregations())) {
                    $filterSet['activeFilters'][$filterKey] = $filterValue;
                }
            }
        }
        $sortingSet = [];
        if (null !== $sort) {
            $sortingSet[] = [
                'active'  => true,
                'sorting' => $sort['to'] ?? 'asc',
                'name'    => $sort['by'] ?? 'submit',
                'visible' => true,
            ];
        }
        $list = [];
        $this->profilerStart('ConvertESHits');
        foreach ($elasticsearchResult->getHits()['hits'] as $hit) {
            $list[] = $this->convertElasticsearchHitToLegacy($hit);
        }
        $this->profilerStop('ConvertESHits');

        $resultSet = new ElasticsearchResultSet();
        $resultSet->setResult($list);
        $resultSet->setFilterSet($filterSet);
        $resultSet->setSortingSet($sortingSet);
        $resultSet->setTotal(count($elasticsearchResult->getHits()['hits'] ?? []));
        $resultSet->setSearchFields($elasticsearchResult->getSearchFields());
        $resultSet->setSearch($search ?? '');
        $resultSet->setPager($elasticsearchResult->getPager());

        return $resultSet->lock();
    }

    /**
     * Konvertiere das Ergebnis aus Elasticsearch zu Legacy.
     *
     * @param array $hit
     */
    protected function convertElasticsearchHitToLegacy($hit)
    {
        $singleHit = $hit['_source'];
        if (array_key_exists('originalId', $singleHit)) {
            $singleHit['original']['ident'] = $singleHit['originalId'];
        }
        if (array_key_exists('parentId', $singleHit)) {
            $singleHit['parent']['ident'] = $singleHit['parentId'];
        }
        if (array_key_exists('elementTitle', $singleHit)) {
            $singleHit['element']['title'] = $singleHit['elementTitle'];
        }
        if (array_key_exists('documentTitle', $singleHit)) {
            $singleHit['document']['title'] = $singleHit['documentTitle'];
        }
        if (array_key_exists('paragraphTitle', $singleHit)) {
            $singleHit['paragraph']['title'] = $singleHit['paragraphTitle'];
        }

        // obscure if user may not read obscured text
        if (false === $this->permissions->hasPermission('feature_obscure_text')) {
            // obscure statement text
            $singleHit['text'] = $this->editorService->obscureString($singleHit['text']);
        }

        return $singleHit;
    }
}
