<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Document;

use Carbon\Carbon;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;

/**
 * Input:
 * Required parameters by this route are the following:
 * ```
 * "params": {
 *   "datetime": String of the designated datetime to switch the state.
 *   "state": Bool of the designated state.
 *   "elementIds": Array<int, string> of IDs of the elements to prepare for automatic state switch.
 * }
 * ```.
 *
 * Output:
 * A JSON-RPC 2.0 Specification conform response object.
 * Contains the following attributes:
 * ```
 * "jsonrpc": String, which specified the version of the  JSON-RPC protocol: 2.0
 * "result": Integer, which holds the number of successfully updated Elements.
 * "error": Integer, which holds the errorcode. Only existing in case of an error.
 * "id": String, which identifies the request and will be the same as in input parameters.
 * ```
 */
class RpcElementsBulkEditor implements RpcMethodSolverInterface
{
    private const ELEMENTS_BULK_EDIT_METHOD = 'planning.document.category.bulk.edit';

    public function __construct(private readonly CurrentUserInterface $currentUser, private readonly ElementsService $elementService, private readonly RpcErrorGenerator $errorGenerator)
    {
    }

    public function supports(string $method): bool
    {
        return self::ELEMENTS_BULK_EDIT_METHOD === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $resultResponse = [];
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);

                $switchDateString = $rpcRequest->params->datetime;
                $designatedSwitchDateTime = Carbon::createFromFormat(Carbon::ATOM, $switchDateString)->toDateTime();
                $designatedState = $rpcRequest->params->state;
                $elementIdsToSwitch = $rpcRequest->params->elementIds;

                $updatedElements = $this->elementService->prepareElementsForAutoSwitchState(
                    $elementIdsToSwitch,
                    $designatedSwitchDateTime,
                    $designatedState,
                    $procedure->getId()
                );

                $resultResponse[] = $this->generateMethodResult($rpcRequest, count($updatedElements));
            } catch (EntityIdNotFoundException|OptimisticLockException) {
                $resultResponse[] = $this->errorGenerator->internalError($rpcRequest);
            } catch (InvalidArgumentException|InvalidSchemaException) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (AccessDeniedException) {
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

    /**
     * @throws UserNotFoundException
     * @throws AccessDeniedException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        $this->validateAccess();
    }

    public function generateMethodResult(object $rpcRequest, int $elementsCount): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $elementsCount;
        $result->id = $rpcRequest->id;

        return $result;
    }

    /**
     * @throws UserNotFoundException
     * @throws AccessDeniedException
     */
    private function validateAccess(): void
    {
        if (!$this->currentUser->hasAllPermissions(
            'area_admin_single_document',
            'feature_admin_element_edit'
        )) {
            throw new AccessDeniedException();
        }
    }
}
