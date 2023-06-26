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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureAccessEvaluator;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;

class NonAuthorizedAssignRemover
{
    /**
     * @var ObjectManager
     */
    private $entityManager;

    public function __construct(
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly EntityFetcher $entityFetcher,
        ManagerRegistry $registry,
        private readonly MessageBagInterface $messageBag,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly ProcedureAccessEvaluator $procedureAccessEvaluator,
        private readonly ProcedureService $procedureService
    ) {
        $this->entityManager = $registry->getManager();
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
            $this->entityContentChangeService->saveEntityChanges($claimable, $claimable::class);

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
            ->map(static fn(User $user): string => $user->getId())
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
                $this->conditionFactory->propertyIsNotNull(['assignee']),
                $this->conditionFactory->propertyHasNotAnyOfValues(
                    $this->getAssignableUserIds($procedure),
                    ['assignee', 'id']
                ),
                $this->conditionFactory->propertyHasValue(
                    $procedure->getId(),
                    ['procedure', 'id']
                ),
            ]
        );
    }
}
