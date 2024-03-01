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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Maps\MapCoordinateDataFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\ValueObject\MapCoordinate;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;
use Throwable;

class ProcedureGeolocatorRpcMethod implements RpcMethodSolverInterface
{
    public function __construct(private readonly CurrentUserInterface $currentUser, private readonly MapCoordinateDataFetcher $mapCoordinateDataFetcher, private readonly RpcErrorGenerator $errorGenerator)
    {
    }

    public function supports(string $method): bool
    {
        return 'procedure.locate' === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $resultResponse[] = $this->generateMethodResult($rpcRequest);
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

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->currentUser->hasPermission('area_main_procedures')) {
            throw new AccessDeniedException();
        }
    }

    private function generateMethodResult($rpcRequest): object
    {
        $mapCoordinate = new MapCoordinate();
        $mapCoordinate->setLatitude($rpcRequest->params->latitude);
        $mapCoordinate->setLongitude($rpcRequest->params->longitude);
        $mapCoordinate->lock();

        try {
            $locationData = $this->mapCoordinateDataFetcher->fetchCoordinateData($mapCoordinate);

            $result = new stdClass();
            $result->jsonrpc = '2.0';
            $result->result = [
                'locationPostalCode' => $locationData->getPostalCode() ?? '',
                'municipalCode'      => $locationData->getMunicipalCode() ?? '',
                'ars'                => $locationData->getArs() ?? '',
                'locationName'       => $locationData->getLocality() ?? '',
            ];
            $result->id = $rpcRequest->id;

            return $result;
        } catch (Throwable) {
            return $this->errorGenerator->serverError($rpcRequest);
        }
    }
}
