<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\SearchParams;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use Webmozart\Assert\Assert;

class RpcSegmentIdLoader implements RpcMethodSolverInterface
{
    final public const SEGMENT_ID_LOAD = 'segment.load.id';

    public function __construct(
        protected readonly DqlConditionFactory $conditionFactory,
        protected readonly DrupalFilterParser $drupalFilterParser,
        protected readonly JsonSchemaValidator $jsonValidator,
        protected readonly StatementSegmentResourceType $segmentResourceType,
        protected readonly JsonApiSortingParser $sortingParser,
        protected readonly JsonApiEsService $jsonApiEsService,
        protected readonly LoggerInterface $logger,
        protected RpcErrorGenerator $errorGenerator
    ) {
    }

    public function supports(string $method): bool
    {
        return self::SEGMENT_ID_LOAD === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $expectedProcedureId = $procedure?->getId();
        Assert::stringNotEmpty($expectedProcedureId);

        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];
        foreach ($rpcRequests as $rpcRequest) {
            try {
                $params = $rpcRequest->params;

                $conditions = $this->getConditions($params, $expectedProcedureId);
                if (isset($params->sort)) {
                    $sortMethods = $this->sortingParser->createFromQueryParamValue($params->sort);
                } else {
                    $sortMethods = [];
                }
                $segmentIdentifiers = $this->segmentResourceType->listEntityIdentifiers($conditions, $sortMethods);
                $searchParams = SearchParams::createOptional(isset($params->search) ? $this->toArray($params->search) : []);
                if ($searchParams instanceof SearchParams) {
                    $elasticsearchResult = $this->jsonApiEsService->getEsFilteredResult(
                        $this->segmentResourceType,
                        $segmentIdentifiers,
                        $searchParams,
                        [] === $sortMethods,
                        null
                    );
                    $esResultArrays = $this->jsonApiEsService->toLegacyResultES($elasticsearchResult);
                    $segmentIdentifiers = array_column($esResultArrays, 'id');
                }

                $resultResponse[] = $this->generateMethodResult($rpcRequest, $segmentIdentifiers);
            } catch (Exception $exception) {
                $this->logger->error('Error while loading segment IDs', ['exception' => $exception]);
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    /**
     * @param list<non-empty-string> $segmentIds
     */
    public function generateMethodResult(object $rpcRequest, array $segmentIds): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $segmentIds;
        $result->id = $rpcRequest->id;

        return $result;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        $params = $rpcRequest->params;
        if (property_exists($params, 'sort')) {
            Assert::stringNotEmpty($params->sort);
        }
        if (property_exists($params, 'filter')) {
            Assert::isArray($params->filter);
        }
        if (property_exists($params, 'search')) {
            Assert::isArray($params->searh);
        }
    }

    private function getConditions(stdClass $params, string $procedureId)
    {
        $drupalFilter = $this->toArray($params->filter);
        $conditions = null === $drupalFilter || [] === $drupalFilter
            ? []
            : $this->drupalFilterParser->parseFilter($this->drupalFilterParser->validateFilter($drupalFilter));
        $conditions[] = $this->conditionFactory->propertyHasValue(
            $procedureId,
            $this->segmentResourceType->parentStatement->procedure->id
        );

        return $conditions;
    }

    private function toArray(stdClass $object): array
    {
        return json_decode(json_encode($object), true);
    }
}
