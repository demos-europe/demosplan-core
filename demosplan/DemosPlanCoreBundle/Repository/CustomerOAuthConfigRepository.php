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
use demosplan\DemosPlanCoreBundle\Entity\User\CustomerOAuthConfig;

class CustomerOAuthConfigRepository extends CoreRepository
{
    public function findByCustomer(CustomerInterface $customer): ?CustomerOAuthConfig
    {
        /** @var CustomerOAuthConfig|null $result */
        $result = $this->findOneBy(['customer' => $customer]);

        return $result;
    }

    public function findByCustomerSubdomain(string $subdomain): ?CustomerOAuthConfig
    {
        return $this->createQueryBuilder('c')
            ->join('c.customer', 'cust')
            ->where('cust.subdomain = :subdomain')
            ->setParameter('subdomain', $subdomain)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
