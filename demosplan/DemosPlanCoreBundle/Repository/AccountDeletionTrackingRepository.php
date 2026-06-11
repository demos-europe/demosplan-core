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

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;

/**
 * @template-extends CoreRepository<AccountDeletionTracking>
 */
class AccountDeletionTrackingRepository extends CoreRepository
{
    public function findOneByUser(UserInterface $user): ?AccountDeletionTracking
    {
        return $this->findOneBy(['user' => $user]);
    }
}
