<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository\StatementImportEmail;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\MaillaneConnection;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Repository\FluentRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MaillaneConnectionRepository extends FluentRepository
{
    /**
     * @var ValidatorInterface
     */
    private $validator;

    public function __construct(
        DqlConditionFactory $dqlConditionFactory,
        ManagerRegistry $registry,
        SortMethodFactory $sortMethodFactory,
        string $entityClass,
        ValidatorInterface $validator)
    {
        parent::__construct($dqlConditionFactory, $registry, $sortMethodFactory, $entityClass);
        $this->validator = $validator;
    }

    /**
     * Fetch procedure by Maillane account ID
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getProcedureByMaillaneAccountId(string $accountId): Procedure
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('procedure')
            ->from(Procedure::class, 'procedure')
            ->join('procedure.maillaneConnection', 'mc')
            ->where('mc.maillaneAccountId = :accountId')
            ->setParameter('accountId', $accountId)
            ->getQuery();

        return $query->getSingleResult();
    }

    /**
     * Create a MaillaneConnection with necessary properties
     * and validate it
     *
     * @throws ViolationsException
     */
    public function createMaillaneConnection(?string $maillaneAccountId, string $recipientMailAddress,$procedure): MaillaneConnection
    {
        $maillaneConnection = new MaillaneConnection();
        $maillaneConnection->setMaillaneAccountId($maillaneAccountId);
        $maillaneConnection->setRecipientEmailAddress($recipientMailAddress);
        $maillaneConnection->setProcedure($procedure);

        // validation
        $violations = $this->validator->validate($maillaneConnection);
        if (0 < count($violations)) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        return $maillaneConnection;
    }
}
