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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\TransactionRequiredException;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\Querying\Contracts\PathException;
use Exception;

/**
 * Checks if there are caseworkers associated with statements that are no longer
 * authorized to work in this procedure. Removes found caseworkers.
 */
class NonAuthorizedCaseworkerRemover
{
    private ProcedureAccessEvaluator $procedureAccessEvaluator;

    private ProcedureService $procedureService;

    private EntityFetcher $entityFetcher;

    private DqlConditionFactory $conditionFactory;

    private MessageBagInterface $messageBag;

    private EntityContentChangeService $entityContentChangeService;

    public function __construct(
        EntityContentChangeService $entityContentChangeService,
        EntityFetcher $entityFetcher,
        MessageBagInterface $messageBag,
        DqlConditionFactory $conditionFactory,
        ProcedureAccessEvaluator $procedureAccessEvaluator,
        ProcedureService $procedureService
    ) {
        $this->procedureAccessEvaluator = $procedureAccessEvaluator;
        $this->procedureService = $procedureService;
        $this->entityFetcher = $entityFetcher;
        $this->conditionFactory = $conditionFactory;
        $this->messageBag = $messageBag;
        $this->entityContentChangeService = $entityContentChangeService;
    }

    public function removeNonAuthorizedCaseWorkers(string $procedureId): void
    {
        try {
            $procedure = $this->procedureService->getProcedureWithCertainty($procedureId);
            $statementsToUnsetCaseworker = $this->getStatementsToUnsetCaseworker($procedure);

            if ([] !== $statementsToUnsetCaseworker) {
                $removedCaseWorkers = $this->unsetCaseWorkers($statementsToUnsetCaseworker);
                $this->messageBag->add(
                    'confirm',
                    'procedure_update.caseworker_autoremove.success',
                    ['removedUsers' => count($removedCaseWorkers)]
                );
            }
        } catch (Exception $e) {
            $this->messageBag->add(
                'error',
                'procedure_update.caseworker_autoremove.error'
            );
        }
    }

    /**
     * Gets an array of unauthorized caseworkers who are associated with statements in this procedure.
     *
     * @return array<int, Statement>
     *
     * @throws PathException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    private function getStatementsToUnsetCaseworker(Procedure $procedure): array
    {
        $authorizedUserNames = $this->getAuthorizedUserNames($procedure);

        return $this->entityFetcher->listEntitiesUnrestricted(
            Statement::class,
            [
                $this->conditionFactory->propertyHasNotValue('', ['meta', 'caseWorkerName']),
                $this->conditionFactory->propertyHasNotAnyOfValues(
                    $authorizedUserNames,
                    ['meta', 'caseWorkerName']
                ),
                $this->conditionFactory->propertyHasValue($procedure->getId(), ['procedure', 'id']),
            ]
        );
    }

    /**
     * Gets an array of all users authorized for this procedure.
     *
     * @return array<int, string>
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
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

    /**
     * Removes caseworkers from the array of statements.
     *
     * @param array<int, Statement> $statementsWithSetCaseWorker
     */
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
