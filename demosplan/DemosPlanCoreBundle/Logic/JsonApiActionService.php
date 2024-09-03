<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiListResultInterface;
use DemosEurope\DemosplanAddon\Contracts\ApiRequest\JsonApiEsServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\ReadableEsResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\ValueObject\ApiListResult;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Doctrine\ORM\Query\QueryException;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\JsonApi\RequestHandling\UrlParameter;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Utilities\Iterables;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class JsonApiActionService
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param DrupalFilterParser<ClauseFunctionInterface<bool>> $filterParser
     * @param JsonApiSortingParser<OrderBySortMethodInterface>  $sortingParser
     */
    public function __construct(
        TraceableEventDispatcher $eventDispatcher,
        private readonly JsonApiEsService $jsonApiEsService,
        private readonly JsonApiPaginationParser $paginationParser,
        private readonly DrupalFilterParser $filterParser,
        private readonly JsonApiSortingParser $sortingParser
    ) {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array<int, ClauseFunctionInterface<bool>> $conditions
     * @param array<int, OrderBySortMethodInterface>    $sortMethods
     *
     * @throws QueryException
     * @throws UserNotFoundException
     */
    public function listObjects(
        JsonApiResourceTypeInterface $type,
        array $conditions,
        array $sortMethods = [],
        ?APIPagination $pagination = null
    ): ApiListResultInterface {
        if (null === $pagination) {
            $filteredEntities = $type->getEntities($conditions, $sortMethods);

            return new ApiListResult($filteredEntities, [], null);
        }

        /** @var DemosPlanPaginator $paginator */
        $paginator = $type->getEntityPaginator($pagination, $conditions, $sortMethods);

        $entities = $paginator->getCurrentPageResults();
        $entities = Iterables::asArray($entities);

        return new ApiListResult($entities, [], null, null, $paginator);
    }

    /**
     * @param ReadableEsResourceTypeInterface&DplanResourceType $type
     * @param array<int, FunctionInterface<bool>>               $conditions
     */
    public function searchObjects(
        ReadableEsResourceTypeInterface $type,
        SearchParams $searchParams,
        array $conditions,
        array $sortMethods = [],
        array $filterAsArray = [],
        bool $requireEntities = true,
        ?APIPagination $pagination = null
    ): ApiListResult {
        // we do not need to apply any sorting here, because it needs to be applied later
        $entityIdentifiers = $type->listEntityIdentifiers($conditions, []);

        return $this->jsonApiEsService->getEsFilteredObjects($type, $entityIdentifiers, $searchParams, $filterAsArray, $requireEntities, $sortMethods, $pagination);
    }

    /**
     * @throws QueryException
     * @throws UserNotFoundException
     */
    public function getObjectsByQueryParams(
        ParameterBag $query,
        ResourceTypeInterface $type
    ): ApiListResult {
        $filters = $this->getFilters($query);
        $sortMethods = $this->getSorting($query);

        return $this->getObjects($type, $filters, $sortMethods, $query);
    }

    protected function getObjects(
        ResourceTypeInterface $type,
        array $filters,
        array $sortMethods,
        ParameterBag $query
    ): ApiListResultInterface {
        $searchParams = SearchParams::createOptional($query->get(JsonApiEsServiceInterface::SEARCH, []));
        $pagination = $this->getPagination($query);

        if (null === $searchParams) {
            return $this->listObjects(
                $type,
                $filters,
                $sortMethods,
                $pagination
            );
        }

        if (!$type instanceof ReadableEsResourceTypeInterface) {
            $typeClass = $type::class;
            throw new InvalidArgumentException("Type does not implement ReadableEsResourceTypeInterface: $typeClass");
        }

        return $this->searchObjects(
            $type,
            $searchParams,
            $filters,
            $sortMethods,
            [],
            true,
            $pagination
        );
    }

    protected function getFilters(ParameterBag $query): array
    {
        if (!$query->has(UrlParameter::FILTER)) {
            return [];
        }

        $filterParam = $query->get(UrlParameter::FILTER);
        $filterParam = $this->filterParser->validateFilter($filterParam);
        $conditions = $this->filterParser->parseFilter($filterParam);
        $query->remove(UrlParameter::FILTER);

        return $conditions;
    }

    /**
     * @return list<OrderBySortMethodInterface>
     */
    protected function getSorting(ParameterBag $query): array
    {
        $sort = $query->get(UrlParameter::SORT);
        $query->remove(UrlParameter::SORT);

        return $this->sortingParser->createFromQueryParamValue($sort);
    }

    protected function getPagination(ParameterBag $query): ?APIPagination
    {
        $pagination = null;
        if ($query->has(UrlParameter::PAGE)) {
            $pagination = $this->paginationParser->parseApiPaginationProfile(
                $query->get(UrlParameter::PAGE, []),
                '', // sorting is done using JsonApiSortingParser
                $query->get(UrlParameter::SIZE, JsonApiPaginationParser::DEFAULT_PAGE_SIZE)
            );
        }

        return $pagination;
    }
}
