<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use Webmozart\Assert\Assert;

class RpcStatementEmailSender implements RpcMethodSolverInterface
{
    final public const STATEMENT_EMAIL_SENDER = 'statement.email.sender';

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        protected readonly LoggerInterface $logger,
        protected RpcErrorGenerator $errorGenerator
    ) {
    }

    public function supports(string $method): bool
    {
        return self::STATEMENT_EMAIL_SENDER === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $expectedProcedureId = $procedure?->getId();
        Assert::stringNotEmpty($expectedProcedureId);

        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];
        foreach ($rpcRequests as $rpcRequest) {
            try {
                $params = $rpcRequest->params;
                $params->subject;
                $params->body;
                $params->emailCC;
                $resultResponse[] = $this->generateMethodResult($rpcRequest, true);
            } catch (Exception $exception) {
                $this->logger->error('Error while sending Email for Statement ', ['exception' => $exception]);
                $resultResponse[] = $this->errorGenerator->serverError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    public function generateMethodResult(object $rpcRequest, bool $emailSent): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = $emailSent;
        $result->id = $rpcRequest->id;

        return $result;
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->currentUser->hasPermission('area_admin_statement_list')) {
            throw new AccessDeniedException();
        }
    }

}
