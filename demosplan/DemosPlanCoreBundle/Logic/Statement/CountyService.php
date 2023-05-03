<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Repository\CountyRepository;
use demosplan\DemosPlanUserBundle\Logic\CustomerService;
use Exception;

class CountyService extends CoreService
{
    /**
     * @var CustomerService
     */
    private $customerService;
    /**
     * @var CountyRepository
     */
    private $countyRepository;

    public function __construct(CountyRepository $countyRepository, CustomerService $customerService)
    {
        $this->countyRepository = $countyRepository;
        $this->customerService = $customerService;
    }

    /**
     * @return array<int, County>
     */
    public function getCounties(): array
    {
        $currentCustomer = $this->customerService->getCurrentCustomer();

        return $this->countyRepository->getAllOfCustomer($currentCustomer);
    }

    /**
     * Returns all counties of the current customer.
     *
     * @return array<int, County>
     *
     * @deprecated use {@link CountyService::getCounties()} instead and handle the exception yourself
     */
    public function getAllCounties(): array
    {
        try {
            return $this->getCounties();
        } catch (Exception $e) {
            $this->logger->warning('Exception on getting all counties of current customer', ['exception' => $e]);

            return [];
        }
    }

    /**
     * Returns all counties as Array.
     *
     * @return array
     */
    public function getAllCountiesAsArray()
    {
        $counties = $this->getAllCounties();

        return \collect($counties)->map(
            function (County $county) {
                return ['id' => $county->getId(), 'name' => $county->getName()];
            }
        )
            ->sortBy('name')
            ->values()
            ->toArray();
    }

    /**
     * Returns a specific county.
     *
     * @param string $id - identifies the county
     *
     * @return County|null
     */
    public function getCounty($id)
    {
        try {
            return $this->countyRepository->get($id);
        } catch (Exception $e) {
            $this->logger->error('Get County with ID: '.$id.' failed: ', [$e]);

            return null;
        }
    }

    /**
     * Returns a county.
     *
     * @param string $name
     *
     * @return \demosplan\DemosPlanCoreBundle\Entity\Statement\County|null
     *
     * @throws \Doctrine\ORM\ORMException
     */
    public function findCountyByName($name)
    {
        return $this->countyRepository->findOneBy(['name' => $name]);
    }
}
