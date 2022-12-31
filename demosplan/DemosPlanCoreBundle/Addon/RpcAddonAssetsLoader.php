<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Addon;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;

class RpcAddonAssetsLoader implements RpcMethodSolverInterface
{
    private PermissionsInterface $permissions;
    private RpcErrorGenerator $errorGenerator;
    private AddonRegistry $addonRegistry;

    public function __construct(
        AddonRegistry $addonRegistry,
        PermissionsInterface $permissions,
        RpcErrorGenerator $errorGenerator)
    {
        $this->permissions = $permissions;
        $this->errorGenerator = $errorGenerator;
        $this->addonRegistry = $addonRegistry;
    }

    public function supports(string $method): bool
    {
        return 'addons.assets.load' === $method;
    }

    public function execute(?Procedure $procedure, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];

        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);

                $hookName = $rpcRequest->params->hookName;
                $addonsAssetsData = $this->addonRegistry->getFrontendClassesForHook($hookName);

                $resultResponse[] = $this->generateMethodResult($rpcRequest, $addonsAssetsData);
            } catch (InvalidArgumentException|InvalidSchemaException $e) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (AccessDeniedException $e) {
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
        if (!$this->permissions->hasPermission('area_admin_consultations')) {
            throw AccessDeniedException::missingPermission('area_admin_consultations');
        }
    }

    private function generateMethodResult(object $rpcRequest, array $assets): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $assets;
        $result->id = $rpcRequest->id;

        return $result;
    }
}
