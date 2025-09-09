<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Statistics\MatomoApi;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class RpcProcedureAnalytics implements RpcMethodSolverInterface
{
    public function __construct(private readonly JsonSchemaValidator $jsonSchemaValidator, private readonly MatomoApi $matomoApi, private readonly PermissionsInterface $permissions, private readonly RpcErrorGenerator $errorGenerator)
    {
    }

    public function supports(string $method): bool
    {
        return 'procedure.analytics.retrieve' === $method;
    }

    /**
     * @param object|array<int, object> $rpcRequests
     *
     * @return array<string, mixed>
     *
     * @throws JsonException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $procedureId = $rpcRequest->params->procedureId;
                if (null === $procedure || $procedure->getId() !== $procedureId) {
                    throw new \demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException('Given procedure ID must match the procedure the user was authorized for.');
                }

                $responseData = $this->matomoApi->getProcedureStatistics($procedureId);

                $resultResponse[] = $this->generateMethodResult($rpcRequest, $responseData);
            } catch (InvalidArgumentException|InvalidSchemaException) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (\demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException) {
                $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
            }
        }

        return $resultResponse;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->permissions->hasPermission('feature_procedure_analytics')) {
            throw new AccessDeniedException();
        }
        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getConfigPath('json-schema/rpc-procedure-analytics-retrieve-schema.json')
        );
    }

    /**
     * @param array<int, array<string, int>> $resultData
     */
    private function generateMethodResult(object $rpcRequest, array $resultData): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $resultData;
        $result->id = $rpcRequest->id;

        return $result;
    }
}
