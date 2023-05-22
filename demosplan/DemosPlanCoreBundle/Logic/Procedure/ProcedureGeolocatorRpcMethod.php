<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Maps\MapCoordinateDataFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\MapCoordinate;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;
use Throwable;

class ProcedureGeolocatorRpcMethod implements RpcMethodSolverInterface
{
    /**
     * @var RpcErrorGenerator
     */
    private $errorGenerator;

    /**
     * @var MapCoordinateDataFetcher
     */
    private $mapCoordinateDataFetcher;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    public function __construct(
        CurrentUserInterface $currentUser,
        MapCoordinateDataFetcher $mapCoordinateDataFetcher,
        RpcErrorGenerator $errorGenerator
    ) {
        $this->currentUser = $currentUser;
        $this->errorGenerator = $errorGenerator;
        $this->mapCoordinateDataFetcher = $mapCoordinateDataFetcher;
    }

    public function supports(string $method): bool
    {
        return 'procedure.locate' === $method;
    }

    public function execute(?Procedure $procedure, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $resultResponse[] = $this->generateMethodResult($rpcRequest);
            } catch (InvalidArgumentException|InvalidSchemaException $e) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (AccessDeniedException|UserNotFoundException $e) {
                $resultResponse[] = $this->errorGenerator->accessDenied($rpcRequest);
            } catch (Exception $e) {
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
        } catch (Throwable $exception) {
            return $this->errorGenerator->serverError($rpcRequest);
        }
    }
}
