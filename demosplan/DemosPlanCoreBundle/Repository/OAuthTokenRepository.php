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
     * Check whether the access token for a user has been refreshed by doing a guaranteed fresh DB lookup.
     *
     * This method bypasses Doctrine's identity map by calling EntityManager::refresh() before
     * checking the token state. It is intended to be called after waiting on a symfony/lock —
     * a concurrent process held the lock and may have already refreshed the tokens successfully.
     * Without the identity-map bypass, Doctrine would return the in-memory entity that still
     * reflects the pre-refresh state, making the concurrent-success check useless.
     *
     * Returns true if the access token exists and has not yet expired, meaning the concurrent
     * refresh succeeded and the caller can skip its own KeyCloak call.
     *
     * @param string $userId The user ID (UUID)
     */
    public function haveTokensBeenRefreshed(string $userId): bool
    {
        $oauthToken = $this->findByUserId($userId);

        if (null === $oauthToken) {
            return false;
        }

        $this->getEntityManager()->refresh($oauthToken);

        $expiresAt = $oauthToken->getAccessTokenExpiresAt();

        return null !== $expiresAt && $expiresAt > new DateTime('now', new DateTimeZone(OAuthToken::TIMEZONE));
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
        $timezone = new DateTimeZone(OAuthToken::TIMEZONE);
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
