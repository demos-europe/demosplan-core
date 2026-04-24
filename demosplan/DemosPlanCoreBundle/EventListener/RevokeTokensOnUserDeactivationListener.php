<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Hard-revokes every active personal access token owned by a user the moment that user's
 * `deleted` flag flips to true. Runs inside the same Doctrine flush that performed the
 * deactivation so the two states are durably consistent — a deactivated user cannot race
 * an API request against their pre-existing token.
 *
 * Intentionally narrow: only reacts to User entities, and only when `deleted` transitioned
 * false → true. Other updates to the user (password change, role change, profile edits)
 * do not trigger revocation to avoid surprising automation users.
 */
class RevokeTokensOnUserDeactivationListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function postUpdate(PostUpdateEventArgs $eventArgs): void
    {
        $entity = $eventArgs->getObject();
        if (!$entity instanceof User) {
            return;
        }

        $em = $eventArgs->getObjectManager();
        $uow = $em->getUnitOfWork();
        $changes = $uow->getEntityChangeSet($entity);
        if (!isset($changes['deleted'])) {
            return;
        }
        [$oldDeleted, $newDeleted] = $changes['deleted'];
        if (true !== (bool) $newDeleted || (bool) $oldDeleted) {
            return;
        }

        $tokenRepository = $em->getRepository(PersonalAccessToken::class);
        $tokens = $tokenRepository->findBy(['user' => $entity, 'revokedAt' => null]);
        if ([] === $tokens) {
            return;
        }

        $now = new DateTime();
        foreach ($tokens as $token) {
            $token->revoke($now);
        }
        $em->flush();

        $this->logger->info('Revoked personal access tokens following user deactivation', [
            'user_id'     => $entity->getId(),
            'token_count' => count($tokens),
        ]);
    }
}
