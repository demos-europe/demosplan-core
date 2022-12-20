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
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
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

                // Here comes the real logic
                $hookName = $rpcRequest->params->hookName;
                $addonsAssetsData = $this->addonRegistry->getFrontendClassesForHook($hookName);

                $loadedAssetsData = [];
                foreach ($addonsAssetsData as $addon => $assetsData) {
                    if (file_exists($assetsData['manifest'])) {

                    }
                }

                // Now it's time to match the files to the paths from the manifest

                // Grab the content of the files and add it to an array with all information
                $assets = [];

                $resultResponse[] = $this->generateMethodResult($rpcRequest, $assets);
            } catch (InvalidArgumentException | InvalidSchemaException $e) {
                $resultResponse[] = $this->errorGenerator->invalidParams($rpcRequest);
            } catch (AccessDeniedException | UserNotFoundException $e) {
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
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
        }
    }

    private function generateMethodResult(object $rpcRequest, array $assets): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = 'ok';
        $result->id = $rpcRequest->id;
        $result->assets = $assets;

        return $result;
    }
}
