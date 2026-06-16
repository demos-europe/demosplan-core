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

use DateTimeInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\LoginAudit;

/**
 * @template-extends CoreRepository<LoginAudit>
 */
class LoginAuditRepository extends CoreRepository
{
    public function persistAndFlush(LoginAudit $audit): void
    {
        $em = $this->getEntityManager();
        $em->persist($audit);
        $em->flush();
    }

    /**
     * Returns true if a successful audit row already exists for the given session
     * and authenticator. Used to deduplicate repeated success events within the
     * same session (e.g. firewall re-firing LoginSuccessEvent on subsequent requests
     * of an already-authenticated session). Failures are intentionally not deduped.
     */
    public function existsSuccessForSessionAndAuthenticator(string $sessionIdHash, string $authenticator): bool
    {
        $count = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.sessionIdHash = :hash')
            ->andWhere('a.authenticator = :authenticator')
            ->andWhere('a.result = :result')
            ->setParameter('hash', $sessionIdHash)
            ->setParameter('authenticator', $authenticator)
            ->setParameter('result', LoginAudit::RESULT_SUCCESS)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function countOlderThan(DateTimeInterface $cutoff): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdDate < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteOlderThan(DateTimeInterface $cutoff): int
    {
        return (int) $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdDate < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
