<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Helpers;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

use function collect;
use function in_array;

class Helpers
{
    /**
     * @var QuestionHelper
     */
    protected $helper;

    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly RoleRepository $roleRepository
    ) {
        $this->helper = new QuestionHelper();
    }

    /**
     * @return array<int, Role>
     */
    public function askRoles(InputInterface $input, OutputInterface $output, array $rolesAllowed): array
    {
        $availableRolesCollection = collect($this->roleRepository->findAll());
        $rolesSelection = $availableRolesCollection
            ->mapWithKeys(function (Role $role) {
                $name = $role->getName();
                $code = $role->getCode();

                return [$code => $name];
            })
            // filter roles that are not allowed
            ->filter(static function (string $name, string $code) use ($rolesAllowed) {
                return in_array($code, $rolesAllowed, true);
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
        $availableCustomers = collect($this->customerRepository->findAll());
        $mappedCustomerInformation = $availableCustomers->mapWithKeys(function (Customer $customer): array {
            $name = $customer->getName();
            $subdomain = $customer->getSubdomain();

            return [$subdomain => $name];
        })
            ->sort()
            ->toArray();
        $questionCustomer = new ChoiceQuestion(
            'Please select a customer: ',
            $mappedCustomerInformation
        );
        $answer = $this->helper->ask($input, $output, $questionCustomer);

        return $availableCustomers->first(fn (Customer $customer) => $answer === $customer->getSubdomain());
    }
}
