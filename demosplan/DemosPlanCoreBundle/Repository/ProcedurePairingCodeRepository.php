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

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePairingCode;

/**
 * @template-extends CoreRepository<ProcedurePairingCode>
 */
class ProcedurePairingCodeRepository extends CoreRepository
{
    public function findByCodeHash(string $codeHash): ?ProcedurePairingCode
    {
        $code = $this->findOneBy(['codeHash' => $codeHash]);

        return $code instanceof ProcedurePairingCode ? $code : null;
    }

    /**
     * @return list<ProcedurePairingCode>
     */
    public function findRedeemableByProcedure(Procedure $procedure, DateTime $now): array
    {
        /** @var list<ProcedurePairingCode> $codes */
        $codes = $this->createQueryBuilder('code')
            ->where('code.procedure = :procedure')
            ->andWhere('code.consumedAt IS NULL')
            ->andWhere('code.expiresAt > :now')
            ->setParameter('procedure', $procedure)
            ->setParameter('now', $now)
            ->orderBy('code.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $codes;
    }

    /**
     * Marks the code consumed and reports whether this call is the one that did it.
     *
     * A conditional UPDATE rather than a read-then-write: two instances redeeming concurrently would
     * both pass an `isConsumed()` check and both get a token. Only the winner gets `true`.
     */
    public function consume(ProcedurePairingCode $code, DateTime $now): bool
    {
        $affectedRows = $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE procedure_pairing_code SET consumed_at = :now WHERE id = :id AND consumed_at IS NULL',
            [
                'now' => $now->format('Y-m-d H:i:s'),
                'id'  => $code->getId(),
            ]
        );

        if (1 !== $affectedRows) {
            return false;
        }

        $code->markConsumed($now);

        return true;
    }

    public function persistAndFlush(ProcedurePairingCode $code): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($code);
        $entityManager->flush();
    }
}
