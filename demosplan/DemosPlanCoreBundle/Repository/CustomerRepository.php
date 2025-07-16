<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * @template-extends CoreRepository<Customer>
 */
class CustomerRepository extends CoreRepository
{
    public function findCustomerById(string $id): Customer
    {
        return $this->find($id);
    }

    /**
     * @return Customer[]
     */
    public function findCustomersByIds(array $ids): array
    {
        return $this->findBy(['id' => $ids]);
    }

    /**
     * @throws CustomerNotFoundException
     */
    public function findCustomerBySubdomain(string $subdomain): Customer
    {
        $result = $this->findOneBy(['subdomain' => $subdomain]);

        if (!$result instanceof Customer) {
            throw CustomerNotFoundException::noSubdomain($subdomain);
        }

        return $result;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addOrga(Customer $customer, Orga $orga): Customer
    {
        if ($customer->addOrga($orga)) {
            $this->getEntityManager()->persist($customer);
            $this->getEntityManager()->flush();
        }

        return $customer;
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function removeOrga(Customer $customer, Orga $orga)
    {
        $customer->removeOrga($orga);
        $this->getEntityManager()->persist($customer);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Customer updatedCustomer
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject(Customer $customer): Customer
    {
        $em = $this->getEntityManager();
        $customer->setMapAttribution(strip_tags($customer->getMapAttribution()));
        $em->persist($customer);
        $em->flush();

        return $customer;
    }
}
