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
use DateTimeZone;
use demosplan\DemosPlanCoreBundle\Entity\User\OAuthToken;

/**
 * Repository for managing OAuth tokens storage and retrieval.
 *
 * @template-extends CoreRepository<OAuthToken>
 */
class OAuthTokenRepository extends CoreRepository
{
    private const TIMEZONE = 'Europe/Berlin';

    /**
     * Find an OAuth token by user ID.
     *
     * @param string $userId The user ID (UUID)
     *
     * @return OAuthToken|null The OAuth token if found, null otherwise
     */
    public function findByUserId(string $userId): ?OAuthToken
    {
        return $this->findOneBy(['user' => $userId]);
    }

    /**
     * Delete OAuth tokens for a specific user.
     *
     * @param string $userId The user ID (UUID)
     */
    public function deleteByUserId(string $userId): void
    {
        $token = $this->findByUserId($userId);
        if (null !== $token) {
            $this->getEntityManager()->remove($token);
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Delete OAuth token entries with outdated pending data (full request buffer or URL-only entry).
     *
     * Removes entire token entries where pendingRequestTimestamp is older than the specified age.
     * This covers both full POST buffers and URL-only entries (set by storePendingPageUrl),
     * since both write a timestamp as an anchor for this cleanup.
     * Stale entries mean the user never re-authenticated after token expiry.
     *
     * @param int $olderThanMinutes Delete entries with pending data older than this many minutes (default: 60 = 1 hour)
     *
     * @return int Number of deleted entries
     */
    public function clearOutdated(int $olderThanMinutes = 60): int
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $threshold = new DateTime("-{$olderThanMinutes} minutes", $timezone);

        $qb = $this->createQueryBuilder('t');
        $qb->where('t.pendingRequestTimestamp IS NOT NULL')
            ->andWhere('t.pendingRequestTimestamp < :threshold')
            ->setParameter('threshold', $threshold);

        $tokens = $qb->getQuery()->getResult();
        $count = 0;

        foreach ($tokens as $token) {
            $this->getEntityManager()->remove($token);
            ++$count;
        }
        $this->getEntityManager()->flush();

        return $count;
    }
}
