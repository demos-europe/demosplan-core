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
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;

/**
 * @template-extends CoreRepository<PersonalAccessToken>
 */
class PersonalAccessTokenRepository extends CoreRepository
{
    public function findByPrefix(string $prefix): ?PersonalAccessToken
    {
        $token = $this->findOneBy(['tokenPrefix' => $prefix]);

        return $token instanceof PersonalAccessToken ? $token : null;
    }

    /**
     * @return list<PersonalAccessToken>
     */
    public function findActiveByUser(User $user, ?Customer $customer = null): array
    {
        $criteria = ['user' => $user, 'revokedAt' => null];
        if (null !== $customer) {
            $criteria['customer'] = $customer;
        }

        /** @var list<PersonalAccessToken> $tokens */
        $tokens = $this->findBy($criteria, ['createdAt' => 'DESC']);

        return $tokens;
    }

    /**
     * @return list<PersonalAccessToken>
     */
    public function findAllByUser(User $user): array
    {
        /** @var list<PersonalAccessToken> $tokens */
        $tokens = $this->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $tokens;
    }

    /**
     * Revokes every non-revoked token owned by the given user (e.g. on deactivation).
     * Returns the number of tokens that were newly revoked.
     */
    public function revokeAllForUser(User $user, DateTime $now, ?User $by = null): int
    {
        $count = 0;
        foreach ($this->findActiveByUser($user) as $token) {
            $token->revoke($now, $by);
            ++$count;
        }
        $this->getEntityManager()->flush();

        return $count;
    }

    public function persistAndFlush(PersonalAccessToken $token): void
    {
        $em = $this->getEntityManager();
        $em->persist($token);
        $em->flush();
    }
}
