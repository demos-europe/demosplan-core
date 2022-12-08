<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use demosplan\plugins\workflow\SegmentsManager\Entity\Segment;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;

class NonAuthorizedAssignRemover
{
    /**
     * @var ProcedureAccessEvaluator
     */
    private $procedureAccessEvaluator;

    /**
     * @var ProcedureService
     */
    private $procedureService;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var DqlConditionFactory
     */
    private $conditionFactory;

    /**
     * @var ObjectManager
     */
    private $entityManager;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var EntityContentChangeService
     */
    private $entityContentChangeService;

    public function __construct(
        EntityContentChangeService $entityContentChangeService,
        EntityFetcher $entityFetcher,
        ManagerRegistry $registry,
        MessageBagInterface $messageBag,
        DqlConditionFactory $conditionFactory,
        ProcedureAccessEvaluator $procedureAccessEvaluator,
        ProcedureService $procedureService
    ) {
        $this->entityManager = $registry->getManager();
        $this->procedureAccessEvaluator = $procedureAccessEvaluator;
        $this->procedureService = $procedureService;
        $this->entityFetcher = $entityFetcher;
        $this->conditionFactory = $conditionFactory;
        $this->messageBag = $messageBag;
        $this->entityContentChangeService = $entityContentChangeService;
    }

    /**
     * @throws MessageBagException
     * @throws ProcedureNotFoundException
     */
    public function removeNonAuthorizedAssignees(string $procedureId): void
    {
        $procedure = $this->procedureService->getProcedureWithCertainty($procedureId);
        $claimablesToUnassign = $this->getClaimablesToUnassign($procedure);

        if ([] !== $claimablesToUnassign) {
            $removedUserIds = $this->removeAssignees($claimablesToUnassign);

            $this->messageBag->add(
                'confirm',
                'procedure_update.assignee_autoremove',
                [
                    'removedUsers' => count($removedUserIds),
                ]
            );

            $this->entityManager->flush();
        }
    }

    /**
     * Removes the assignee from the given entities and saves it in the version history.
     *
     * @param array<int, Statement|Segment> $claimables
     *
     * @return array<int, string> removed user IDs
     */
    private function removeAssignees(array $claimables): array
    {
        $removedAssigneeIds = array_map(function (Statement $claimable): string {
            // assignee can't be null, due to the query used
            $assigneeId = $claimable->getAssignee()->getId();

            $claimable->setAssignee(null);
            $this->entityContentChangeService->saveEntityChanges($claimable, get_class($claimable));

            return $assigneeId;
        }, $claimables);

        return array_unique($removedAssigneeIds);
    }

    /**
     * @return array<int, string>
     */
    private function getAssignableUserIds(Procedure $procedure): array
    {
        $ownsProcedureCondition = $this->procedureAccessEvaluator->getOwnsProcedureCondition($procedure);
        $authorizedUsers = $this->procedureService->getAuthorizedUsers($procedure->getId());
        $owningUsers = $this->entityFetcher->listEntitiesUnrestricted(User::class, [$ownsProcedureCondition]);

        return $authorizedUsers
            ->merge($owningUsers)
            ->map(static function (User $user): string {
                return $user->getId();
            })
            ->unique()
            ->all();
    }

    /**
     * Get all statements and segments that are assigned to a user no longer allowed to be
     * used as assignee in the given procedure.
     *
     * @return array<int, Statement|Segment>
     */
    private function getClaimablesToUnassign(Procedure $procedure): array
    {
        return $this->entityFetcher->listEntitiesUnrestricted(
        // Fetches not only statements but child classes too (i.e. segments)
            Statement::class,
            [
                $this->conditionFactory->propertyIsNotNull('assignee'),
                $this->conditionFactory->propertyHasNotAnyOfValues(
                    $this->getAssignableUserIds($procedure),
                    'assignee', 'id'
                ),
                $this->conditionFactory->propertyHasValue(
                    $procedure->getId(),
                    'procedure', 'id'
                ),
            ]
        );
    }

    public function removeNonAuthorizedCaseWorkers(string $procedureId): void
    {
        $procedure = $this->procedureService->getProcedureWithCertainty($procedureId);
        $statementsToUnsetCaseworker = $this->getStatementsToUnsetCaseworker($procedure);

        if ([] !== $statementsToUnsetCaseworker) {
            $removedCaseWorkers = $this->unsetCaseWorkers($statementsToUnsetCaseworker);
            $this->messageBag->add(
                'confirm',
                'procedure_update.caseworker_autoremove',
                [
                    'removedUsers' => count($removedCaseWorkers),
                ]
            );

        }
    }

    private function getStatementsToUnsetCaseworker(Procedure $procedure): array
    {
        $authorizedUserNames = $this->getAuthorizedUserNames($procedure);

        return $this->entityFetcher->listEntitiesUnrestricted(
            Statement::class,
            [
                $this->conditionFactory->propertyHasNotValue('', 'meta', 'caseWorkerName'),
                $this->conditionFactory->propertyHasNotAnyOfValues($authorizedUserNames,
                    'meta', 'caseWorkerName'
                ),
                $this->conditionFactory->propertyHasValue($procedure->getId(), 'procedure', 'id')
            ]
        );
    }

    private function getAuthorizedUserNames(Procedure $procedure): array
    {
        $ownsProcedureCondition = $this->procedureAccessEvaluator->getOwnsProcedureCondition($procedure);
        $authorizedUsers = $this->procedureService->getAuthorizedUsers($procedure->getId());
        $owningUsers = $this->entityFetcher->listEntitiesUnrestricted(User::class, [$ownsProcedureCondition]);

        return $authorizedUsers
            ->merge($owningUsers)
            ->map(static function (User $user): string {
                return $user->getName();
            })
            ->unique()
            ->all();
    }

    private function unsetCaseWorkers(array $statementsWithSetCaseWorker): array
    {
        $unsetCaseWorkerNames = array_map(function (Statement $statementWithSetCaseWorker): string {
            $caseWorkerName = $statementWithSetCaseWorker->getMeta()->getCaseWorkerName();

            $statementWithSetCaseWorker->getMeta()->setCaseWorkerName('');
            $this->entityContentChangeService->saveEntityChanges(
                $statementWithSetCaseWorker,
                get_class($statementWithSetCaseWorker)
            );

            return $caseWorkerName;
        }, $statementsWithSetCaseWorker);

        return array_unique($unsetCaseWorkerNames);
    }
}
