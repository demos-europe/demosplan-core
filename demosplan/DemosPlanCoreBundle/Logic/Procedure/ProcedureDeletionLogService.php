<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Procedure;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureDeletionLog;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

class ProcedureDeletionLogService
{
    final public const SYSTEM_ACTOR_NAME = 'System';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function logSoftDelete(Procedure $procedure, UserInterface $user): void
    {
        $logEntry = (new ProcedureDeletionLog())
            ->setProcedureId($procedure->getId())
            ->setProcedureName($procedure->getName())
            ->setIsBlueprint($procedure->isMasterTemplate())
            ->setDeletedByUserId($user->getId())
            ->setDeletedByUserFirstName($user->getFirstname())
            ->setDeletedByUserLastName($user->getLastname())
            ->setDeletedByUserEmail($user->getEmail())
            ->setIsHardDeleted(false)
            ->setDeletedAt(new DateTime());

        try {
            $this->entityManager->persist($logEntry);
            $this->entityManager->flush();
        } catch (Exception $exception) {
            $this->logger->error('Failed to write soft-delete log entry for procedure', [
                'procedureId' => $procedure->getId(),
                'exception'   => $exception,
            ]);
        }
    }

    public function logHardDelete(Procedure $procedure): void
    {
        $logEntry = (new ProcedureDeletionLog())
            ->setProcedureId($procedure->getId())
            ->setProcedureName($procedure->getName())
            ->setIsBlueprint($procedure->isMasterTemplate())
            ->setDeletedByUserId(null)
            ->setDeletedByUserFirstName(self::SYSTEM_ACTOR_NAME)
            ->setDeletedByUserLastName(self::SYSTEM_ACTOR_NAME)
            ->setDeletedByUserEmail(null)
            ->setIsHardDeleted(true)
            ->setDeletedAt(new DateTime());

        try {
            $this->entityManager->persist($logEntry);
            $this->entityManager->flush();
        } catch (Exception $exception) {
            $this->logger->error('Failed to write hard-delete log entry for procedure', [
                'procedureId' => $procedure->getId(),
                'exception'   => $exception,
            ]);
        }
    }
}
