<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Helpers;

use function collect;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanUserBundle\Repository\CustomerRepository;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use function in_array;

class Helpers
{
    /**
     * @var QuestionHelper
     */
    protected $helper;

    /**
     * @var RoleRepository
     */
    private $roleRepository;
    /**
     * @var CustomerRepository
     */
    private $customerRepository;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        CustomerRepository $customerRepository,
        GlobalConfigInterface $globalConfig,
        RoleRepository $roleRepository
    ) {
        $this->roleRepository = $roleRepository;
        $this->helper = new QuestionHelper();
        $this->customerRepository = $customerRepository;
        $this->globalConfig = $globalConfig;
    }

    /**
     * @return array<int, Role>
     */
    public function askRoles(InputInterface $input, OutputInterface $output): array
    {
        $availableRolesCollection = collect($this->roleRepository->findAll());
        $rolesSelection = $availableRolesCollection
            ->mapWithKeys(function (Role $role) {
                $name = $role->getName();
                $code = $role->getCode();

                return [$code => $name];
            })
            ->all();
        $questionRoles = new ChoiceQuestion(
            'Please select the users roles. Multiselect is possible with comma separation (example: RMOPSA,RTSUPP): ',
            $rolesSelection
        );
        $questionRoles->setMultiselect(true);
        $answer = $this->helper->ask($input, $output, $questionRoles);

        return $availableRolesCollection->filter(static function (Role $role) use ($answer) {
            $code = $role->getCode();

            return in_array($code, $answer, true);
        })->all();
    }

    public function askCustomer(InputInterface $input, OutputInterface $output): Customer
    {
        $availableCustomer = collect($this->customerRepository->findAll());
        $customerSelection = $availableCustomer
            ->filter(function (Customer $customer): bool {
                return in_array($customer->getSubdomain(), $this->globalConfig->getSubdomainsAllowed(), true);
            })
            ->mapWithKeys(function (Customer $customer): array {
                $name = $customer->getName();
                $subdomain = $customer->getSubdomain();

                return [$subdomain => $name];
            })
            ->toArray();
        $questionCustomer = new ChoiceQuestion(
            'Please select a customer: ',
            $customerSelection
        );
        $answer = $this->helper->ask($input, $output, $questionCustomer);

        return $availableCustomer->first(function (Customer $customer) use ($answer) {
            return $answer === $customer->getSubdomain();
        });
    }
}
