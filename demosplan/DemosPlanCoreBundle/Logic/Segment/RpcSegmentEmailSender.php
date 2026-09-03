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
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use Webmozart\Assert\Assert;

class RpcSegmentEmailSender implements RpcMethodSolverInterface
{
    final public const SEGMENT_EMAIL_SENDER = 'segment.email.sender';

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        protected readonly LoggerInterface $logger,
        protected RpcErrorGenerator $errorGenerator,
        private readonly SegmentEmailSender $segmentEmailSender,
        private readonly TransactionService $transactionService,
    ) {
    }

    public function supports(string $method): bool
    {
        return self::SEGMENT_EMAIL_SENDER === $method;
    }

    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        $expectedProcedureId = $procedure?->getId();
        Assert::stringNotEmpty($expectedProcedureId);

        return $this->transactionService->executeAndFlushInTransaction(
            fn (): array => $this->processRequests($expectedProcedureId, $rpcRequests)
        );
    }

    private function processRequests(string $procedureId, $rpcRequests): array
    {
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        $resultResponse = [];
        foreach ($rpcRequests as $rpcRequest) {
            try {
                $this->validateRpcRequest($rpcRequest);
                $params = $rpcRequest->params;
                $emailIsSent = $this->segmentEmailSender->sendSegmentsMail(
                    $params->segmentIds,
                    $procedureId,
                    $params->recipientMail,
                    $params->subject,
                    $params->body,
                    $params->sendEmailCC,
                    $params->replyTo ?? null
                );

                $resultResponse[] = $this->generateMethodResult($rpcRequest, $emailIsSent);
            } catch (Exception $exception) {
                $this->logger->error('Error while sending Email for segment ', ['exception' => $exception]);
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
        if (!$this->currentUser->hasPermission('feature_segment_send_via_mail')) {
            throw new AccessDeniedException();
        }
    }
}
