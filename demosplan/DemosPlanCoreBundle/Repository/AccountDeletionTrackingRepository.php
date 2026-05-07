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
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

/**
 * @template-extends CoreRepository<AccountDeletionTracking>
 */
class AccountDeletionTrackingRepository extends CoreRepository
{
    public function findOneByUser(UserInterface $user): ?AccountDeletionTracking
    {
        return $this->findOneBy(['user' => $user]);
    }

    /**
     * Returns active users whose effective inactivity reference (`lastLogin` if set,
     * else `createdDate`) is at or before the cutoff. Excludes deleted users and the
     * given protected IDs (typically the anonymous-user constant plus any project-
     * specific protected accounts). The AI API user is excluded by login at the
     * policy level rather than here, since its row ID is random per project.
     *
     * @param list<string> $protectedUserIds
     *
     * @return list<UserInterface>
     */
    public function findInactivityDeletionCandidates(
        DateTimeInterface $cutoff,
        array $protectedUserIds,
    ): array {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.deleted = false')
            ->andWhere('COALESCE(u.lastLogin, u.createdDate) <= :cutoff')
            ->setParameter('cutoff', $cutoff);

        if ([] !== $protectedUserIds) {
            $queryBuilder
                ->andWhere('u.id NOT IN (:protectedUserIds)')
                ->setParameter('protectedUserIds', $protectedUserIds);
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
