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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\PendingPermission;
use Doctrine\ORM\NoResultException;

/**
 * Repository for managing pending permissions.
 *
 * @template-extends CoreRepository<PendingPermission>
 */
class PendingPermissionRepository extends CoreRepository
{
    /**
     * Find all pending permissions for a specific customer and organization type.
     *
     * @param CustomerInterface $customer The customer to query for
     * @param string            $orgaType The organization type (e.g., 'PLANNING_AGENCY')
     *
     * @return array<int, PendingPermission> Array of pending permissions
     */
    public function findByCustomerAndOrgaType(CustomerInterface $customer, string $orgaType): array
    {
        return $this->findBy([
            'customer' => $customer,
            'orgaType' => $orgaType,
        ]);
    }

    /**
     * Find all pending permissions for a specific customer.
     *
     * @param CustomerInterface $customer The customer to query for
     *
     * @return array<int, PendingPermission> Array of pending permissions
     */
    public function findByCustomer(CustomerInterface $customer): array
    {
        return $this->findBy(['customer' => $customer]);
    }

    /**
     * Check if a specific pending permission already exists.
     *
     * @param CustomerInterface $customer   The customer
     * @param string            $permission The permission name
     * @param string            $roleCode   The role code
     * @param string            $orgaType   The organization type
     *
     * @return bool True if the pending permission exists
     */
    public function exists(
        CustomerInterface $customer,
        string $permission,
        string $roleCode,
        string $orgaType,
    ): bool {
        try {
            $count = $this->createQueryBuilder('pp')
                ->select('COUNT(pp.id)')
                ->where('pp.customer = :customer')
                ->andWhere('pp.permission = :permission')
                ->andWhere('pp.roleCode = :roleCode')
                ->andWhere('pp.orgaType = :orgaType')
                ->setParameter('customer', $customer)
                ->setParameter('permission', $permission)
                ->setParameter('roleCode', $roleCode)
                ->setParameter('orgaType', $orgaType)
                ->getQuery()
                ->getSingleScalarResult();

            return $count > 0;
        } catch (NoResultException) {
            return false;
        }
    }

    /**
     * Delete all pending permissions for a customer.
     *
     * @param CustomerInterface $customer The customer
     *
     * @return int Number of deleted entries
     */
    public function deleteByCustomer(CustomerInterface $customer): int
    {
        return $this->createQueryBuilder('pp')
            ->delete()
            ->where('pp.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete pending permissions marked for auto-deletion for a specific customer and org type.
     *
     * @param CustomerInterface $customer The customer
     * @param string            $orgaType The organization type
     *
     * @return int Number of deleted entries
     */
    public function deleteAutoDeleteByCustomerAndOrgaType(
        CustomerInterface $customer,
        string $orgaType,
    ): int {
        return $this->createQueryBuilder('pp')
            ->delete()
            ->where('pp.customer = :customer')
            ->andWhere('pp.orgaType = :orgaType')
            ->andWhere('pp.autoDelete = :autoDelete')
            ->setParameter('customer', $customer)
            ->setParameter('orgaType', $orgaType)
            ->setParameter('autoDelete', true)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete a specific pending permission.
     *
     * @param CustomerInterface $customer   The customer
     * @param string            $permission The permission name
     * @param string            $roleCode   The role code
     * @param string            $orgaType   The organization type
     *
     * @return int Number of deleted entries
     */
    public function deleteSpecific(
        CustomerInterface $customer,
        string $permission,
        string $roleCode,
        string $orgaType,
    ): int {
        return $this->createQueryBuilder('pp')
            ->delete()
            ->where('pp.customer = :customer')
            ->andWhere('pp.permission = :permission')
            ->andWhere('pp.roleCode = :roleCode')
            ->andWhere('pp.orgaType = :orgaType')
            ->setParameter('customer', $customer)
            ->setParameter('permission', $permission)
            ->setParameter('roleCode', $roleCode)
            ->setParameter('orgaType', $orgaType)
            ->getQuery()
            ->execute();
    }

    /**
     * Get statistics about pending permissions per customer.
     *
     * @return array<int, array{customer_name: string, pending_count: int}> Statistics grouped by customer
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('pp')
            ->select('c.name as customer_name', 'COUNT(pp.id) as pending_count')
            ->join('pp.customer', 'c')
            ->groupBy('c.id')
            ->orderBy('pending_count', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
