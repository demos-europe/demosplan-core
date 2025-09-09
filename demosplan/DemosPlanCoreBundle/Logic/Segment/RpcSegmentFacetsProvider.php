<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use DemosEurope\DemosplanAddon\Contracts\ApiRequest\JsonApiEsServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use demosplan\DemosPlanCoreBundle\Transformers\Filters\AggregationFilterTypeTransformer;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;

class RpcSegmentFacetsProvider implements RpcMethodSolverInterface
{
    private const FACET_LIST_METHOD = 'segments.facets.list';

    public function __construct(private readonly ApiResourceService $resourceService, private readonly CurrentUserInterface $currentUser, private readonly DrupalFilterParser $filterParser, private readonly JsonApiActionService $jsonApiActionService, private readonly RpcErrorGenerator $errorGenerator, private readonly StatementSegmentResourceType $segmentResourceType)
    {
    }

    public function supports(string $method): bool
    {
        return self::FACET_LIST_METHOD === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $facetKey = $rpcRequest->params->path;
                $filterAsArray = Json::decodeToArray(Json::encode($rpcRequest->params->filter));
                $filterAsArray = $this->filterParser->validateFilter($filterAsArray);
                $conditions = $this->filterParser->parseFilter($filterAsArray);
                $searchPhrase = $rpcRequest->params->searchPhrase;
                $searchPhrase = null === $searchPhrase || empty($searchPhrase)
                    ? null
                    : $searchPhrase;

                $searchParams = SearchParams::createOptional([
                    JsonApiEsServiceInterface::VALUE      => $searchPhrase,
                    JsonApiEsServiceInterface::FACET_KEYS => [$facetKey => $facetKey],
                ]);
                $apiListResult = $this->jsonApiActionService->searchObjects(
                    $this->segmentResourceType,
                    $searchParams,
                    $conditions,
                    [],
                    $filterAsArray,
                    false
                );
                $aggregationFilterType = $apiListResult->getFacets();
                $item = $this->resourceService->makeCollection($aggregationFilterType, AggregationFilterTypeTransformer::class);
                $jsonArray = $this->resourceService->getFractal()->createData($item)->toArray();
                $jsonArray['meta']['count'] = $apiListResult->getResultCount();

                /*
                 * When the user selects a filter, the BE uses ElasticSearch facets to create the filters and answers back the FE with the given filter id and the property selected = true (see here demosplan\DemosPlanCoreBundle\Logic\ApiRequest\Facet\FacetFactory.php method createAggregationFilterItems)
                 * When filtering for assignee NULL, there is no id, consequently there is not Facet for this filter.
                 * Because NULL filters lack an id, the BE can't inform the FE that this filter (assignee == NULL) has been selected.
                 * To address this, we include information about the NULL filter in the meta (metadata) of the RPC request: $jsonArray['meta']['unassigned_selected']
                 * This way, the FE is notified that a NULL filter for a assignee is selected, even though it doesn't have an id.
                 */

                $jsonArray['meta']['unassigned_selected'] = property_exists($rpcRequest->params->filter, 'unassigned');

                $resultResponse[] = $this->generateMethodResult($rpcRequest, $jsonArray);
            } catch (InvalidArgumentException|InvalidSchemaException) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (AccessDeniedException|UserNotFoundException) {
                $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
            } catch (Exception) {
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    public function generateMethodResult(object $rpcRequest, array $resultArray): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $resultArray;
        $result->id = $rpcRequest->id;

        return $result;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->currentUser->hasPermission('area_statement_segmentation')) {
            throw new AccessDeniedException();
        }
    }
}
