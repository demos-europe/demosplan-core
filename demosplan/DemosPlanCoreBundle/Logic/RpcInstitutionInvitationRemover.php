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
use DemosEurope\DemosplanAddon\Utilities\Json;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\Exception\OrgaNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\ORM\EntityManager;
use Exception;
use Psr\Log\LoggerInterface;
use stdClass;
use Webmozart\Assert\Assert;

use function sprintf;

class RpcInstitutionInvitationRemover implements RpcMethodSolverInterface
{
    public const SUPPORTED_METHOD_NAME = 'invitedInstitutions.bulk.delete';

    public function __construct(
        private readonly MessageBagInterface $messageBag,
        private readonly LoggerInterface $logger,
        private readonly RpcErrorGeneratorInterface $rpcErrorGenerator,
        private readonly TransactionService $transactionService,
        private readonly CurrentUserInterface $currentUser,
        private readonly ProcedureService $procedureService,
        private readonly JsonSchemaValidator $jsonSchemaValidator,
    ) {
    }

    public function supports(string $method): bool
    {
        return self::SUPPORTED_METHOD_NAME === $method;
    }

    /**
     * This method handles the removal of multiple institutions from a procedure's invitation list.
     */
    public function execute(?ProcedureInterface $procedure, $rpcRequests): array
    {
        if (!$this->currentUser->hasPermission('area_admin_invitable_institution')) {
            $this->logger->error('User does not have permission to manage invited institutions');

            return [$this->rpcErrorGenerator->internalError()];
        }

        if (!$procedure instanceof ProcedureInterface) {
            $this->logger->error('No procedure provided for invited institution deletion');

            return [$this->rpcErrorGenerator->internalError()];
        }

        try {
            return $this->transactionService->executeAndFlushInTransaction(
                fn (EntityManager $entityManager): array => $this->handleExecute($procedure, $rpcRequests)
            );
        } catch (Exception $e) {
            $this->logger->error(
                'An error occurred trying to remove invited institutions via RpcInstitutionInvitationRemover',
                ['ExceptionMessage' => $e->getMessage(), 'Exception' => $e]
            );
            $this->messageBag->add('error', 'warning.invited.institutions.bulk.delete.generic.error');

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
                /** @var array<int, array{id: string}> $institutionIds */
                $institutionIds = $rpcRequest->params->ids ?? null;
                Assert::isIterable($institutionIds, 'expected params->ids to be a list of institution IDs');

                $invitedInstitutions = $procedure->getOrganisation();
                $institutionsToRemove = [];

                foreach ($institutionIds as $institutionData) {
                    $institutionId = $institutionData->id ?? '';
                    Assert::stringNotEmpty($institutionId, 'institutionId is expected to be a non-empty string');

                    $institution = $invitedInstitutions->filter(
                        static fn ($org) => $org->getId() === $institutionId
                    )->first();

                    if (false === $institution) {
                        $errorMessage = sprintf('Institution %s is not invited to procedure %s', $institutionId, $procedure->getId());
                        $this->logger->error($errorMessage);
                        $this->messageBag->add('error', 'error.institution.not.invited');
                        throw new OrgaNotFoundException($errorMessage);
                    }

                    $institutionsToRemove[] = $institution;
                }

                if ([] !== $institutionsToRemove) {
                    $this->procedureService->detachOrganisations($procedure, $institutionsToRemove);
                    $this->logger->info(sprintf('Removed %d institutions from procedure %s', count($institutionsToRemove), $procedure->getId()));
                }

                $resultResponse[] = $this->generateMethodSuccessResult($rpcRequest);
                $this->messageBag->add('confirm', 'confirm.invitable_institutions.deleted');
            } catch (Exception $e) {
                $this->messageBag->add('error', 'warning.invited.institutions.bulk.delete.generic.error');
                $this->logger->error(
                    'An error occurred trying to remove invited institutions via RpcInstitutionInvitationRemover',
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
            throw new AccessDeniedException('User does not have permission to manage invited institutions');
        }

        $this->jsonSchemaValidator->validate(
            Json::encode($rpcRequest),
            DemosPlanPath::getConfigPath('json-schema/rpc-invited-institutions-bulk-delete-schema.json')
        );
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
