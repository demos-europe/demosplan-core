<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanStatementBundle\Exception\InvalidDataException;
use demosplan\DemosPlanProcedureBundle\Exception\ProcedureCoupleTokenAlreadyUsedException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProcedureCoupleTokenRepository extends FluentRepository
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
        ValidatorInterface $validator
    ) {
        parent::__construct($dqlConditionFactory, $registry, $sortMethodFactory, $entityClass);
        $this->validator = $validator;
    }

    /**
     * @throws ViolationsException
     */
    public function createAndFlushEntity(Procedure $sourceProcedure, string $token): ProcedureCoupleToken
    {
        $entity = new ProcedureCoupleToken($sourceProcedure, $token);
        $em = $this->getEntityManager();
        $em->persist($entity);
        $violations = $this->validator->validate($entity);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }
        $em->flush();

        return $entity;
    }

    /**
     * Returns the count of {@link ProcedureCoupleToken} instances in the database that have
     * the given {@link Procedure} instance set as {@link ProcedureCoupleToken::$sourceProcedure}
     * or {@link ProcedureCoupleToken::$targetProcedure}, excluding the token instance that
     * matches the `$ignoreTokenId` parameter from the count.
     *
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getTokenCountWithProcedure(Procedure $procedure, ?string $ignoreTokenId): int
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $queryBuilder
            ->select('count(token.id)')
            ->from(ProcedureCoupleToken::class, 'token')
            ->andWhere($queryBuilder->expr()->orX(
                'token.sourceProcedure = :procedure',
                'token.targetProcedure = :procedure'
            ))
            ->setParameter('procedure', $procedure->getId());

        if (null !== $ignoreTokenId) {
            $queryBuilder
                ->andWhere('token.id <> :ignoreTokenId')
                ->setParameter('ignoreTokenId', $ignoreTokenId);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    /**
     * @throws InvalidDataException
     * @throws ProcedureCoupleTokenAlreadyUsedException
     * @throws ResourceNotFoundException
     */
    public function coupleProcedure(Procedure $targetProcedure, string $tokenString): ProcedureCoupleToken
    {
        $token = $this->findOneBy(['token' => $tokenString]);

        if (!$token instanceof ProcedureCoupleToken) {
            throw ResourceNotFoundException::createResourceNotFoundException('ProcedureCoupleToken', $tokenString);
        }

        if ($token->getTargetProcedure() instanceof Procedure) {
            throw ProcedureCoupleTokenAlreadyUsedException::createFromTokenValue($token->getToken());
        }

        if ($token->getSourceProcedure()->isDeleted()) {
            throw new InvalidDataException('Given procedure is deleted.');
        }

        $violations = $this->validator->validate($token);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $token->setTargetProcedure($targetProcedure);
        $this->getEntityManager()->flush();

        return $token;
    }

    public function getTokenForCoupledProcedure(Procedure $sourceProcedure): ?ProcedureCoupleToken
    {
        return $this->createQueryBuilder('token')
            ->where('token.sourceProcedure = :procedureId')
            ->andWhere('token.targetProcedure IS NOT NULL')
            ->setParameter('procedureId', $sourceProcedure->getId())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
