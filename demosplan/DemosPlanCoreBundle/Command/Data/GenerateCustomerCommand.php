<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\EntryAlreadyExistsException;
use demosplan\DemosPlanUserBundle\Repository\CustomerRepository;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class GenerateCustomerCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:data:generate-customer';
    protected static $defaultDescription = 'Creates a new customer';
    /**
     * @var QuestionHelper
     */
    protected $helper;
    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    public function __construct(
        CustomerRepository $customerRepository,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->customerRepository = $customerRepository;
        $this->helper = new QuestionHelper();
    }

    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $name = $this->askCustomerName($input, $output);
        $subdomain = $this->askSubdomain($input, $output);

        $customer = new Customer($name, $subdomain);

        try {
            // create customer
            $this->customerRepository->updateObject($customer);

            $output->writeln(
                'Customer successfully created!',
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            // Print Exception
            $output->writeln(
                'Something went wrong during customer creation: '.$e->getMessage(),
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::FAILURE;
        }
    }

    private function askCustomerName(InputInterface $input, OutputInterface $output): string
    {
        $questionName = new Question('Please enter the Full Name of the customer:', 'default');

        $questionName->setValidator(function ($answer) {
            $existingCustomer = $this->customerRepository->findOneBy(['name' => $answer]);
            if (null !== $existingCustomer) {
                throw new EntryAlreadyExistsException('This name is already used as a customer, please choose another one.');
            }

            return $answer;
        });

        return $this->helper->ask($input, $output, $questionName);
    }

    private function askSubdomain(InputInterface $input, OutputInterface $output): string
    {
        $questionSubdomain = new Question('Please enter the Subdomain of the customer:', 'default');

        $questionSubdomain->setValidator(function ($answer) {
            $existingCustomer = $this->customerRepository->findOneBy(['subdomain' => $answer]);
            if (null !== $existingCustomer) {
                throw new EntryAlreadyExistsException('This subdomain is already used as a customer, please choose another one.');
            }

            return $answer;
        });

        return $this->helper->ask($input, $output, $questionSubdomain);
    }
}
