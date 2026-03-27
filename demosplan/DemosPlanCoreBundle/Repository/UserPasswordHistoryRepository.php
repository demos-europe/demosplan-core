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

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserPasswordHistory;
use Doctrine\ORM\EntityRepository;

class UserPasswordHistoryRepository extends EntityRepository
{
    /**
     * @return UserPasswordHistory[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function deleteExceedingEntries(User $user, int $maxEntries): void
    {
        $entries = $this->findBy(
            ['user' => $user],
            ['createdDate' => 'ASC']  // oldest first
        );

        $countToDelete = count($entries) - $maxEntries;
        for ($i = 0; $i < $countToDelete; $i++) {
            $this->getEntityManager()->remove($entries[$i]);
        }
    }
}
