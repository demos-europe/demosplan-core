<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Validate\JsonSchemaValidator;
use stdClass;

/**
 * Input:
 * Required parameters by this route are the following:
 * ```
 * "params": {
 *   "entity": String of the target entity (statementSegment, procedure, etc)
 *   "function": String of the area of use. Allowed Values: filter, sort, sort_default, search
 *   "accessGroup": String of the user restriction. Allowed Values: all, intern, extern, planner
 * }
 * ```.
 *
 * Output:
 * A JSON-RPC 2.0 Specification conform response object.
 * Contains the following attributes:
 * ```
 * "jsonrpc": String, which specified the version of the  JSON-RPC protocol: 2.0
 * "result": Array<string, string> of all possible search fields with their translation keys
 * "error": Integer, which holds the errorcode. Only existing in case of an error.
 * "id": String, which identifies the request and will be the same as in input parameters.
 * ```
 */
class RpcElasticsearchDefinitionFetcher implements RpcMethodSolverInterface
{
    /**
     * @var ElasticSearchDefinitionProvider
     */
    private $definitionProvider;
    /**
     * @var JsonSchemaValidator
     */
    private $jsonSchemaValidator;

    public function __construct(
        ElasticSearchDefinitionProvider $definitionProvider,
        JsonSchemaValidator $jsonSchemaValidator
    ) {
        $this->definitionProvider = $definitionProvider;
        $this->jsonSchemaValidator = $jsonSchemaValidator;
    }

    public function supports(string $method): bool
    {
        return 'elasticsearchFieldDefinition.provide' === $method;
    }

    public function execute(?Procedure $procedure, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            $this->validateRpcRequest($rpcRequest);

            $definitions = $this->definitionProvider->getAvailableFields(
                $rpcRequest->params->entity,
                $rpcRequest->params->function,
                $rpcRequest->params->accessGroup);

            $resultResponse[] = $this->generateMethodResult($rpcRequest, $definitions);
        }

        return $resultResponse;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getRootPath(
                'demosplan/DemosPlanCoreBundle/Resources/config/json-schema/rpc-elasticsearch-definition-fetcher-schema.json'
            )
        );
    }

    private function generateMethodResult(object $rpcRequest, array $orderMapping): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $orderMapping;
        $result->id = $rpcRequest->id;

        return $result;
    }
}
