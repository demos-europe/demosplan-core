<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;

class UserRoleInCustomerRepository extends CoreRepository
{
    /**
     * Delete all existing user roles for this customer.
     *
     * @param string $userId
     * @param string $customerId
     *
     * @return UserRoleInCustomer|null
     */
    public function clearUserRoles($userId, $customerId)
    {
        return $this->getEntityManager()->createQueryBuilder()
                ->delete(UserRoleInCustomer::class, 'relation')
                ->where('relation.user = :userId')
                ->setParameter('userId', $userId)
                ->andWhere('relation.customer = :customerId')
                ->setParameter('customerId', $customerId)
                ->getQuery()
                ->execute();
    }
}
