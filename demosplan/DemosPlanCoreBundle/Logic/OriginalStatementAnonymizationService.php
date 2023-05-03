<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
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
    /**
     * @var OriginalStatementAnonymizationRepository
     */
    private $originalStatementAnonymizationRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;

    public function __construct(
        OriginalStatementAnonymizationRepository $originalStatementAnonymizationRepository,
        UserRepository $userRepository
    ) {
        $this->originalStatementAnonymizationRepository = $originalStatementAnonymizationRepository;
        $this->userRepository = $userRepository;
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
