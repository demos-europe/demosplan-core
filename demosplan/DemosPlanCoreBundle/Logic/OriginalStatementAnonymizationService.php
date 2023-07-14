<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\OriginalStatementAnonymization;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OriginalStatementAnonymizationRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class OriginalStatementAnonymizationService extends CoreService
{
    public function __construct(private readonly OriginalStatementAnonymizationRepository $originalStatementAnonymizationRepository, private readonly UserRepository $userRepository)
    {
    }

    /**
     * @throws NoResultException
     */
    public function createFromParameters(
        User $createdBy,
        Statement $statement,
        bool $attachmentsDeleted,
        bool $textPassagesAnonymized,
        bool $textVersionHistoryDeleted,
        bool $submitterAndAuthorAnonymized
    ): OriginalStatementAnonymization {
        $entity = new OriginalStatementAnonymization();
        $entity->setAttachmentsDeleted($attachmentsDeleted);
        $entity->setCreatedBy($this->userRepository->get($createdBy->getId()));
        $entity->setStatement($statement);
        $entity->setTextPassagesAnonymized($textPassagesAnonymized);
        $entity->setTextVersionHistoryDeleted($textVersionHistoryDeleted);
        $entity->setSubmitterAndAuthorMetaDataAnonymized($submitterAndAuthorAnonymized);

        return $entity;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function persist(OriginalStatementAnonymization $statementAnonymization): void
    {
        $this->originalStatementAnonymizationRepository->persistAndDelete([$statementAnonymization], []);
    }
}
