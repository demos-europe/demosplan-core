<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * Deletes Segments within procedure.
 *
 * You find general RPC API usage information
 * {@link http://dplan-documentation.ad.berlin.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}.
 * Accepted parameters by this route are the following:
 * ```
 * "params": {
 *   "segmentIds": <JSON array of segmentIds>,
 * }
 * ```
 * the segmentIds field is required, however the array segmentIds may be empty.
 */
class RpcBulkDeleteSegments implements RpcMethodSolverInterface
{
    final public const RPC_JSON_SCHEMA_PATH = 'json-schema/rpc-segments-bulk-delete-schema.json';

    final public const SEGMENTS_BULK_DELETE_METHOD = 'segments.bulk.delete';

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly JsonSchemaValidator $jsonValidator,
        private readonly RpcErrorGenerator $errorGenerator,
        private readonly SegmentHandler $segmentHandler,
        private readonly TransactionService $transactionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supports(string $method): bool
    {
        return self::SEGMENTS_BULK_DELETE_METHOD === $method;
    }

    /**
     * @param array<mixed>|object $rpcRequests
     *
     * @throws ConnectionException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        return $this->transactionService->executeAndFlushInTransaction(
            fn (): array => $this->prepareAction($procedure, $rpcRequests)
        );
    }

    public function isTransactional(): bool
    {
        return true;
    }

    /**
     * @throws InvalidSchemaException
     * @throws JsonException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        $this->validateRpcRequestJson($rpcRequest);
    }

    /**
     * @throws AccessDeniedException
     */
    private function checkIfAuthorized(): bool
    {
        try {
            if (!$this->currentUser->hasPermission('area_statement_segmentation')) {
                $this->logger->warning('User attempted bulk delete of segments without permission', [
                    'userId'             => $this->currentUser->getUser()?->getId(),
                    'requiredPermission' => 'area_statement_segmentation',
                ]);
                throw AccessDeniedException::missingPermission('area_statement_segmentation', $this->currentUser->getUser());
            }

            return true;
        } catch (UserNotFoundException $e) {
            $this->logger->error('User not found during segment bulk delete authorization check', [
                'exceptionMessage' => $e->getMessage(), 'exception' => $e,
            ]);
            throw new AccessDeniedException('User not found');
        }
    }

    private function getJsonSchemaPath(): string
    {
        return DemosPlanPath::getConfigPath(self::RPC_JSON_SCHEMA_PATH);
    }

    private function handleSegmentAction(array $segmentIds): bool
    {
        foreach ($segmentIds as $segmentId) {
            if (!$this->segmentHandler->delete($segmentId)) {
                return false;
            }
        }

        return true;
    }

    private function generateMethodResult(object $rpcRequest): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = 'ok';
        $result->id = $rpcRequest->id;

        return $result;
    }

    /**
     * @throws InvalidSchemaException
     * @throws JsonException
     */
    private function validateRpcRequestJson(object $rpcRequest): void
    {
        $this->jsonValidator->validate(
            Json::encode($rpcRequest),
            $this->getJsonSchemaPath()
        );
    }

    /**
     * @param array<mixed>|object $rpcRequests
     */
    private function prepareAction(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $procedureId = $procedure->getId() ?? 'No Procedure provided for RPC route: '.self::SEGMENTS_BULK_DELETE_METHOD;

        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        try {
            $this->checkIfAuthorized();
        } catch (AccessDeniedException $e) {
            $this->logger->warning('Access denied for segment bulk delete', [
                'procedureId' => $procedureId,
                'exception'   => $e->getMessage(),
            ]);

            return array_map($this->errorGenerator->accessDenied(...), $rpcRequests);
        }

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $segmentIds = $rpcRequest->params->segmentIds;

                if (false === $this->handleSegmentAction($segmentIds)) {
                    $this->logger->error('Failed to handle segment action during bulk delete', [
                        'procedureId' => $procedureId,
                        'segmentIds'  => $segmentIds,
                    ]);
                    $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);

                    return $resultResponse;
                }

                $resultResponse[] = $this->generateMethodResult($rpcRequest);
            } catch (InvalidSchemaException|JsonException $e) {
                $this->logger->warning('Invalid RPC request parameters for segment bulk delete', [
                    'procedureId' => $procedureId,
                    'exception'   => $e->getMessage(),
                ]);
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (Exception $e) {
                $this->logger->error('Unexpected exception during segment bulk delete', [
                    'procedureId' => $procedureId,
                    'exception'   => $e->getMessage(),
                    'trace'       => $e->getTraceAsString(),
                ]);
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }
}
