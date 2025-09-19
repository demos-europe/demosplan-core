<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverStrategy;
use Exception;
use GuzzleHttp\Exception\InvalidArgumentException;
use JsonException;
use JsonSchema\Exception\InvalidSchemaException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GenericRpcController extends BaseController
{
    /**
     * @DplanPermissions("feature_json_rpc_post")
     */
    #[Route(path: '/rpc/2.0', methods: ['POST'], name: 'rpc_generic_post', options: ['expose' => true])]
    public function postAction(
        CurrentProcedureService $currentProcedureService,
        Request $request,
        RpcErrorGenerator $errorGenerator,
        RpcMethodSolverStrategy $rpcMethodSolverStrategy
    ): JsonResponse {
        try {
            $procedure = $currentProcedureService->getProcedure();

            $result = $rpcMethodSolverStrategy->executeMethodSolver(
                $procedure,
                $request->getContent()
            );

            return new JsonResponse($result);
        } catch (Exception $e) {
            return $this->handleException($errorGenerator, $e);
        }
    }

    private function handleException(
        RpcErrorGenerator $errorGenerator,
        Exception $e
    ): JsonResponse {
        $this->logger->error('RPC Route Exception', [$e]);

        if ($e instanceof InvalidSchemaException
            || $e instanceof InvalidArgumentException
            || $e instanceof JsonException) {
            return new JsonResponse($errorGenerator->parseError(), 400);
        }
        if ($e instanceof AccessDeniedException) {
            return new JsonResponse($errorGenerator->accessDenied(), 403);
        }

        return new JsonResponse($errorGenerator->serverError(), 500);
    }
}
