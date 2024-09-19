<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiListResultInterface;
use DemosEurope\DemosplanAddon\Contracts\ApiRequest\JsonApiEsServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet\FacetFactory;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Traits\DI\ElasticsearchQueryTrait;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\ValueObject\ApiListResult;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Pagination\PagePagination;
use EDT\Querying\Utilities\Iterables;
use Elastica\Index;
use Webmozart\Assert\Assert;

class JsonApiEsService implements JsonApiEsServiceInterface
{
    use ElasticsearchQueryTrait;

    /**
     * @param array<string,Index> $searchTypes
     */
    public function __construct(
        private readonly DqlConditionFactory $conditionFactory,
        private readonly FacetFactory $facetFactory,
        private readonly array $searchTypes
    ) {
    }

    public function getEsFilteredResult(
        ReadableEsResourceTypeInterface $resourceType,
        array $prefilteredIdentifiers,
        SearchParams $searchParams,
        bool $scoredSort,
        ?APIPagination $pagination
    ): array {
        $query = $resourceType->getQuery();
        $type = $resourceType->getSearchType();

        // set scopes
        $scopes = $resourceType->getScopes();
        $query->setScopes($scopes);

        // set ID filter
        $query->addFilterShould('id', $prefilteredIdentifiers);
        $searchPhrase = $searchParams->getSearchPhrase();
        $searchFields = $searchParams->getFieldsToSearch();
        if (null !== $searchFields && null === $searchPhrase) {
            throw new InvalidArgumentException("Given fieldsToSearch without given searchPhrase in 'value' is invalid.");
        }

        // set search phrase and fields to search in
        if (null !== $searchPhrase) {
            $esQuerySearch = $query->getAvailableSearch();
            $esQuerySearch->setSearchTerm($searchPhrase);
            if (null !== $searchFields) {
                $esQuerySearch->limitFieldsByNames($searchFields);
            }
            $query->setSearch($esQuerySearch);
        }

        // We can only paginate via Elasticsearch if its sorting is to be used,
        // otherwise we will fetch the content over all pages from Elasticsearch
        // and paginate it later when the entities are fetched from Doctrine.
        if (null !== $pagination && $scoredSort) {
            $page = $pagination->getNumber();
            $limit = $pagination->getSize();
        } else {
            $page = 1;
            $limit = -1;
        }

        // avoid getting a result if no identifiers remained from the pre-filtering, because
        // in this case no filters are set in the query and we would get all documents
        if ([] === $prefilteredIdentifiers) {
            $limit = 0;
        }

        // get raw elasticsearch result
        return $this->getElasticsearchResult($query, $limit, $page, $type);
    }

    /**
     * Accesses the Elasticsearch index to determine the result. Depending on the provided {@link SearchParams}
     * will behave differently to provide the requested data.
     *
     * Regarding the sorting of entities (if requested): if no specific `$sortMethods` were provided
     * the score based sorting determined in the Elasticsearch index will be used to sort entities.
     * However, if at least one {@link SortMethodInterface} was provided, then the sorting will be
     * executed in the relational database and the scored sorting is lost.
     *
     * This sorting behavior explicitly ignores both {@link AbstractQuery::getSortDefault} and
     * {@link TransferableTypeInterface::getDefaultSortMethods()}, as it is assumed that when the
     * `search` parameter was provided by the client (which resulted in this method being called)
     * that the scored sorting is wanted as default.
     *
     * @param ReadableEsResourceTypeInterface&DplanResourceType $resourceType
     * @param array<int, string>                                $prefilteredIdentifiers the IDs of the entities to load
     *
     * @return ApiListResult contains an array of objects corresponding to the given
     *                       {@link ResourceTypeInterface} of the given name as the primary result
     *                       and an arbitrary meta information array as a secondary result
     *
     * @throws InvalidArgumentException given fieldsToSearch without given searchPhrase is an invalid
     *                                  argument-combination, because we ca not know the intention.
     *                                  In order to avoid wrong results (and recognize as such),
     *                                  the exception is necessary.
     *
     * @see http://dplan-documentation.demos-europe.eu/development/application-architecture/elasticsearch/generic-facet-search.html
     */
    public function getEsFilteredObjects(
        ReadableEsResourceTypeInterface $resourceType,
        array $prefilteredIdentifiers,
        SearchParams $searchParams,
        array $rawFilter,
        bool $requireEntities,
        array $sortMethods,
        ?APIPagination $pagination
    ): ApiListResult {
        $scoredSort = [] === $sortMethods;
        $elasticsearchResult = $this->getEsFilteredResult(
            $resourceType,
            $prefilteredIdentifiers,
            $searchParams,
            $scoredSort,
            $pagination
        );

        $esResultArrays = $this->toLegacyResultES($elasticsearchResult);

        // calculate facets
        $facets = null;
        $facetKeys = $searchParams->getFacetKeys();
        if ([] !== $facetKeys) {
            $aggregationBuckets = $elasticsearchResult['aggregations'] ?? [];
            $missingResourcesSums = $elasticsearchResult['aggregationsMissing'] ?? [];
            $facetDefinitions = $resourceType->getFacetDefinitions();
            $facetDefinitions = array_intersect_key($facetDefinitions, $facetKeys);
            $facets = $this->facetFactory->getFacets($facetDefinitions, $aggregationBuckets, $missingResourcesSums, $rawFilter);
        }

        // get additional information
        // If the array fields are missing for some reason we assume zero hits.
        $totalHits = $elasticsearchResult['hits']['total']['value'] ?? 0;
        $paginator = null;
        if (null !== $pagination) {
            // get the paginator of the Elasticsearch query
            /** @var DemosPlanPaginator $paginator */
            $paginator = $elasticsearchResult['pager'];
        }

        // prepare fetching real entities from Doctrine
        $esIds = array_column($esResultArrays, 'id');
        $condition = [] === $esIds
            ? $this->conditionFactory->false()
            : $this->conditionFactory->propertyHasAnyOfValues($esIds, $resourceType->id);

        $entities = [];
        if ($scoredSort) {
            // With scored sorting (no matter if pagination was requested), we need to fetch
            // all entities corresponding to the IDs from Doctrine and re-apply the scored
            // sorting from the Elasticsearch result.
            if ($requireEntities) {
                $entities = $resourceType->getEntities([$condition], []);
                $entities = $this->useIdAsKey($entities);
                $entities = self::sortAndFilterByKeys($esIds, $entities);
                $entities = array_values($entities);
            }
        } elseif (null === $pagination) {
            // Without pagination and without scored sorting, we need to fetch all
            // entities corresponding to the IDs from Doctrine with the requested
            // sorting. The sorting of the Elasticsearch result doesn't matter.
            if ($requireEntities) {
                $entities = $resourceType->getEntities([$condition], $sortMethods);
            }
        } else {
            // With pagination but without scored sorting, we need to fetch all
            // entities corresponding to the IDs of that page from Doctrine with
            // the requested sorting in a paginated manner.
            // The sorting of the Elasticsearch result doesn't matter.
            $paginator = $resourceType->getEntityPaginator($pagination, [$condition], $sortMethods);
            if ($requireEntities) {
                $entities = Iterables::asArray($paginator->getCurrentPageResults());
            }
        }

        return new ApiListResult($entities, [], $facets, $totalHits, $paginator);
    }

    public function getElasticaTypeForTypeName(string $typeName): Index
    {
        if (!isset($this->searchTypes) || !$this->searchTypes[$typeName] instanceof Index) {
            throw new InvalidArgumentException("Invalid type name: {$typeName}");
        }

        return $this->searchTypes[$typeName];
    }

    /**
     * Sorts an array by its keys by using the sorting of another array in which these keys
     * are the values.
     *
     * Example: if you pass
     * * `['c' => 1, 'a' => 2, 'd' => 0]`
     * * and `[2 => 'foo', 1 => 'bar', 3 => 'baz']`
     *
     * as parameters, then you will get `['c' => 'bar', 'a' => 'foo']`, which is the sorting
     * of the keys in the first array with the corresponding values of the second array.
     *
     * @template I of int|string
     * @template K of int|string
     * @template V
     *
     * @param array<I, K> $sortedKeys        the keys to be included in the result with their sorting
     * @param array<K, V> $arrayValuesToSort the array to sort by its keys, keys not present as value in $sortedKeys will be filtered out
     *
     * @return array<I, V>
     */
    public static function sortAndFilterByKeys(array $sortedKeys, array $arrayValuesToSort): array
    {
        // kick out entries in $sortedKeys that have no equivalent in $arrayValuesToSort
        $sortedKeys = array_intersect($sortedKeys, array_keys($arrayValuesToSort));

        return array_map(static fn ($key) => $arrayValuesToSort[$key], $sortedKeys);
    }

    /**
     * @template T of UuidEntityInterface
     *
     * @param array<int, T> $entities
     *
     * @return array<string, T>
     */
    private function useIdAsKey(array $entities): array
    {
        return collect($entities)
            ->mapWithKeys(static fn (UuidEntityInterface $entity): array => [$entity->getId() => $entity])
            ->all();
    }

    public function optimisticallyGetEsFilteredObjects(
        JsonApiResourceTypeInterface $type,
        array $prefilteredIdentifiers,
        string $searchValue,
        ?array $fieldsToSearch,
        array $sortMethods,
        ?PagePagination $pagination
    ): ApiListResultInterface {
        Assert::isInstanceOf($type, ReadableEsResourceTypeInterface::class);

        $searchParamArray = [
            JsonApiEsServiceInterface::VALUE => $searchValue,
        ];
        if (null !== $fieldsToSearch) {
            $searchParamArray[JsonApiEsServiceInterface::FIELDS_TO_SEARCH] = $fieldsToSearch;
        }
        $searchParams = new SearchParams($searchParamArray);

        if (null === $pagination) {
            $apiPagination = null;
        } else {
            $apiPagination = new APIPagination();
            $apiPagination->setSize($pagination->getSize());
            $apiPagination->setNumber($pagination->getNumber());
            $apiPagination->lock();
        }

        return $this->getEsFilteredObjects($type, $prefilteredIdentifiers, $searchParams, [], true, $sortMethods, $apiPagination);
    }
}
