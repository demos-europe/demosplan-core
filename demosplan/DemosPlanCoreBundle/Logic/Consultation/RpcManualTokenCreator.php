<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Consultation;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;

class RpcManualTokenCreator implements RpcMethodSolverInterface
{
    /**
     * @var RpcErrorGenerator
     */
    private $errorGenerator;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    /**
     * @var ConsultationTokenService
     */
    private $consultationTokenService;

    /**
     * @var JsonSchemaValidator
     */
    private $jsonSchemaValidator;

    public function __construct(
        PermissionsInterface $permissions,
        RpcErrorGenerator $errorGenerator,
        ConsultationTokenService $consultationTokenService,
        JsonSchemaValidator $jsonSchemaValidator
    ) {
        $this->errorGenerator = $errorGenerator;
        $this->permissions = $permissions;
        $this->consultationTokenService = $consultationTokenService;
        $this->jsonSchemaValidator = $jsonSchemaValidator;
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

                $this->consultationTokenService->createTokenStatement(
                    $rpcRequest->params->submitterName,
                    $rpcRequest->params->submitterEmailAddress,
                    $rpcRequest->params->note,
                    $rpcRequest->params->submitterCity,
                    $rpcRequest->params->submitterPostalCode,
                    $rpcRequest->params->submitterStreet,
                    $rpcRequest->params->submitterHouseNumber
                );

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

    /**
     * @throws UserNotFoundException
     */
    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->permissions->hasPermission('area_admin_consultations')) {
            throw new \Symfony\Component\Security\Core\Exception\AccessDeniedException();
        }
        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Resources/config/json-schema/rpc-token-create-schema.json')
        );
    }

    public function generateMethodResult(object $rpcRequest): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = 'ok';
        $result->id = $rpcRequest->id;

        return $result;
    }

    public function supports(string $method): bool
    {
        return 'consultationToken.manual.create' === $method;
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
