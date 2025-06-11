<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcErrorGeneratorInterface;
use DemosEurope\DemosplanAddon\Logic\Rpc\RpcMethodSolverInterface;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\OrgaNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use Webmozart\Assert\Assert;

use function sprintf;

class RpcInvitedOrganisationDeleter implements RpcMethodSolverInterface
{
    public const DELETE_INVITED_ORGANISATIONS_METHOD = 'bulk.delete.invited.organisations';

    public function __construct(
        private readonly MessageBagInterface $messageBag,
        private readonly LoggerInterface $logger,
        private readonly RpcErrorGeneratorInterface $rpcErrorGenerator,
        private readonly TransactionService $transactionService,
        private readonly CurrentUserInterface $currentUser,
        private readonly ProcedureService $procedureService,
    ) {
    }

    public function supports(string $method): bool
    {
        return self::DELETE_INVITED_ORGANISATIONS_METHOD === $method;
    }

    /**
     * This method handles the removal of multiple organisations from a procedure's invitation list.
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        if (!$this->currentUser->hasPermission('area_admin_invitable_institution')) {
            $this->logger->error('User does not have permission to manage invited organisations');

            return [$this->rpcErrorGenerator->internalError()];
        }

        if (null === $procedure) {
            $this->logger->error('No procedure provided for invited organisation deletion');

            return [$this->rpcErrorGenerator->internalError()];
        }

        try {
            return $this->transactionService->executeAndFlushInTransaction(
                fn (EntityManager $entityManager): array => $this->handleExecute($procedure, $rpcRequests)
            );
        } catch (Exception $e) {
            $this->logger->error(
                'An error occurred trying to remove invited organisations via RpcInvitedOrganisationDeleter',
                ['ExceptionMessage' => $e->getMessage(), 'Exception' => $e]
            );
            $this->messageBag->add('error', 'warning.invited.organisations.bulk.delete.generic.error');

            return [$this->rpcErrorGenerator->internalError($rpcRequests)];
        }
    }

    private function handleExecute(ProcedureInterface $procedure, $rpcRequests): array
    {
        $resultResponse = [];
        $rpcRequests = is_object($rpcRequests)
            ? [$rpcRequests]
            : $rpcRequests;

        foreach ($rpcRequests as $rpcRequest) {
            try {
                /** @var array<int, array{id: string}> $organisationIds */
                $organisationIds = $rpcRequest->params->ids ?? null;
                Assert::isIterable($organisationIds, 'expected params->ids to be a list of organisation IDs');

                $invitedOrganisations = $procedure->getOrganisation();
                $organisationsToRemove = [];

                foreach ($organisationIds as $organisationData) {
                    $organisationId = $organisationData->id ?? '';
                    Assert::stringNotEmpty($organisationId, 'organisationId is expected to be a non-empty string');

                    $organisation = $invitedOrganisations->filter(
                        static fn ($org) => $org->getId() === $organisationId
                    )->first();

                    if (false === $organisation) {
                        $errorMessage = sprintf('Organisation %s is not invited to procedure %s', $organisationId, $procedure->getId());
                        $this->logger->error($errorMessage);
                        $this->messageBag->add('error', 'error.organisation.not.invited');
                        throw new OrgaNotFoundException($errorMessage);
                    }

                    $organisationsToRemove[] = $organisation;
                }

                if (0 < count($organisationsToRemove)) {
                    $this->procedureService->detachOrganisations($procedure, $organisationsToRemove);
                    $this->logger->info(sprintf('Removed %d organisations from procedure %s', count($organisationsToRemove), $procedure->getId()));
                }

                $resultResponse[] = $this->generateMethodSuccessResult($rpcRequest);
                $this->messageBag->add('confirm', 'confirm.invitable_institutions.deleted');
            } catch (Exception $e) {
                $this->messageBag->add('error', 'warning.invited.organisations.bulk.delete.generic.error');
                $this->logger->error(
                    'An error occurred trying to remove invited organisations via RpcInvitedOrganisationDeleter',
                    ['ExceptionMessage' => $e->getMessage(), 'Exception' => $e]
                );
                $resultResponse[] = $this->rpcErrorGenerator->internalError($rpcRequest);
            }
        }

        return $resultResponse;
    }

    public function isTransactional(): bool
    {
        return true;
    }

    public function validateRpcRequest(object $rpcRequest): void
    {
        if (!$this->currentUser->hasPermission('area_admin_invitable_institution')) {
            throw new AccessDeniedException('User does not have permission to manage invited organisations');
        }
    }

    private function generateMethodSuccessResult(object $rpcRequest): object
    {
        $result = new stdClass();
        $result->jsonrpc = '2.0';
        $result->result = true;
        $result->id = $rpcRequest->id;

        return $result;
    }
}
