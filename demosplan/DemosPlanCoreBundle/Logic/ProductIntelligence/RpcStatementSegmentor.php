<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ProductIntelligence;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Validate\JsonSchemaValidator;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\addons\workflow\SegmentsManager\Logic\Segment\PiSegmentRecognitionRequester;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use stdClass;

class RpcStatementSegmentor implements RpcMethodSolverInterface
{
    public const SEGMENT_STATEMENTS_METHOD = 'segment.statement';

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    /**
     * @var JsonSchemaValidator
     */
    private $jsonSchemaValidator;

    /**
     * @var StatementHandler
     */
    private $statementHandler;

    /**
     * @var PiSegmentRecognitionRequester
     */
    private $piSegmentRecognitionRequester;

    /**
     * @var RpcErrorGenerator
     */
    private $errorGenerator;

    public function __construct(
        PermissionsInterface $permissions,
        RpcErrorGenerator $errorGenerator,
        StatementHandler $statementHandler,
        JsonSchemaValidator $jsonSchemaValidator,
        PiSegmentRecognitionRequester $piSegmentRecognitionRequester
    ) {
        $this->errorGenerator = $errorGenerator;
        $this->permissions = $permissions;
        $this->jsonSchemaValidator = $jsonSchemaValidator;
        $this->statementHandler = $statementHandler;
        $this->piSegmentRecognitionRequester = $piSegmentRecognitionRequester;
    }

    public function supports(string $method): bool
    {
        return self::SEGMENT_STATEMENTS_METHOD === $method;
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

                $statement = $this->statementHandler->getStatementWithCertainty($rpcRequest->params->statementId);
                $this->piSegmentRecognitionRequester->request($statement);

                $resultResponse[] = $this->generateMethodResult($rpcRequest);
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
        if (!$this->permissions->hasPermission('feature_ai_generated_draft_segments')) {
            throw new AccessDeniedException();
        }

        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Resources/config/json-schema/rpc-segment-statement-schema.json')
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
}
