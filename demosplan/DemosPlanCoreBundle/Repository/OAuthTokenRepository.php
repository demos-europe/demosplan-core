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
    public function haveTokensBeenRefreshed(string $userId, DateTimeZone $timezone): bool
    {
        $oauthToken = $this->findByUserId($userId);

        if (null === $oauthToken) {
            return false;
        }

        $this->getEntityManager()->refresh($oauthToken);

        $expiresAt = $oauthToken->getAccessTokenExpiresAt();

        return null !== $expiresAt && $expiresAt > new DateTime('now', $timezone);
    }

    /**
     * Delete stale OAuth token entries.
     *
     * @param int $olderThanMinutes Delete pending entries older than this many minutes (default: 60)
     *
     * @return int Number of deleted entries
     */
    public function clearOutdated(DateTimeZone $timezone, int $olderThanMinutes = 60): int
    {
        $pendingThreshold = new DateTime("-{$olderThanMinutes} minutes", $timezone);

        // 8 hours = 6h max PHP session lifetime (SESSION_LIFETIME) + 2h buffer to avoid
        // cutting off tokens that might still be relevant for future features
        $tokenThreshold = new DateTime('-8 hours', $timezone);

        $qb = $this->getEntityManager()->createQueryBuilder();

        // A pending request was buffered but the user never re-authenticated within the threshold
        $stalePendingRequest = $qb->expr()->andX(
            't.pendingRequestTimestamp IS NOT NULL',
            't.pendingRequestTimestamp < :pendingThreshold'
        );

        // The session was never explicitly closed (e.g. browser just closed) but the
        // expired tokens are beyond the session lifetime + buffer and of no use anymore
        $abandonedSession = $qb->expr()->andX(
            't.accessTokenExpiresAt IS NOT NULL',
            't.accessTokenExpiresAt < :tokenThreshold',
            't.pendingRequestTimestamp IS NULL'
        );

        // DQL DELETE returns int (affected rows) or throws an exception — cast for static analysis
        return (int) $qb->delete(OAuthToken::class, 't')
            ->where($qb->expr()->orX($stalePendingRequest, $abandonedSession))
            ->setParameter('pendingThreshold', $pendingThreshold)
            ->setParameter('tokenThreshold', $tokenThreshold)
            ->getQuery()
            ->execute();
    }
}
