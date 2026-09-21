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

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;

/**
 * @template-extends FluentRepository<OrgaStatusInCustomer>
 */
class OrgaStatusInCustomerRepository extends FluentRepository
{
    /**
     * Deletes relation rows that reference an orga, orga type or customer no
     * longer present in the database. Such orphans can only survive bulk SQL
     * imports that ran with foreign key checks disabled.
     *
     * The bulk delete bypasses the UnitOfWork, so already loaded
     * OrgaStatusInCustomer instances and Customer::$orgaStatuses collections
     * are stale afterwards.
     */
    public function deleteOrphanedOrgaRelations(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->where('NOT EXISTS (SELECT o.id FROM '.Orga::class.' o WHERE o.id = r.orga)')
            ->orWhere('NOT EXISTS (SELECT ot.id FROM '.OrgaType::class.' ot WHERE ot.id = r.orgaType)')
            ->orWhere('NOT EXISTS (SELECT c.id FROM '.Customer::class.' c WHERE c.id = r.customer)')
            ->getQuery()
            ->execute();
    }
}
