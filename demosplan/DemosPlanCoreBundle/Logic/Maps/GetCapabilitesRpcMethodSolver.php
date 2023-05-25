<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Maps;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\ExternalDataFetchException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use JsonException;
use stdClass;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GetCapabilitesRpcMethodSolver implements RpcMethodSolverInterface
{
    public const METHOD = 'map.get_capabilities';

    /**
     * @var MapCapabilitiesLoader
     */
    private $mapCapabilitiesLoader;

    /**
     * @var JsonSchemaValidator
     */
    private $jsonSchemaValidator;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    public function __construct(JsonSchemaValidator $jsonSchemaValidator, MapCapabilitiesLoader $mapCapabilitiesLoader, PermissionsInterface $permissions)
    {
        $this->mapCapabilitiesLoader = $mapCapabilitiesLoader;
        $this->jsonSchemaValidator = $jsonSchemaValidator;
        $this->permissions = $permissions;
    }

    public function supports(string $method): bool
    {
        return self::METHOD === $method;
    }

    public function execute(?Procedure $procedure, $rpcRequests): array
    {
        $response = [];

        foreach ($rpcRequests as $rpcRequest) {
            $this->validateRpcRequest($rpcRequest);

            $response[] = $this->doRequest($rpcRequest->id, $rpcRequest->params->url);
        }

        return $response;
    }

    public function isTransactional(): bool
    {
        return true;
    }

    /**
     * @throws JsonException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->permissions->hasPermission('area_map_participation_area')) {
            throw new AccessDeniedException();
        }

        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest, JSON_THROW_ON_ERROR),
            DemosPlanPath::getConfigPath('config/json-schema/rpc-map-get-capbilities-schema.json')
        );
    }

    private function doRequest(string $id, string $url): object
    {
        $response = new stdClass();

        $response->id = $id;
        $response->jsonrpc = '2.0';

        try {
            $capabilitiesResponse = $this->mapCapabilitiesLoader->getCapabilities($url);

            $response->result = [
                'xml'  => $capabilitiesResponse->getXml(),
                'type' => $capabilitiesResponse->getType(),
            ];
        } catch (ExternalDataFetchException $e) {
            $response->error = [
                'code'    => -32000,
                'message' => $e->getMessage(),
            ];
        }

        return $response;
    }
}
