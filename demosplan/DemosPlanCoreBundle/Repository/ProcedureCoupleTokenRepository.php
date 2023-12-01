<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureCoupleToken;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureCoupleTokenAlreadyUsedException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @template-extends CoreRepository<ProcedureCoupleToken>
 */
class ProcedureCoupleTokenRepository extends CoreRepository
{
    /**
     * @throws ViolationsException
     */
    public function createAndFlushEntity(Procedure $sourceProcedure, string $token): ProcedureCoupleToken
    {
        $entity = new ProcedureCoupleToken($sourceProcedure, $token);
        $em = $this->getEntityManager();
        $this->validate($entity);
        $em->persist($entity);
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

        $this->validate($token);

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
