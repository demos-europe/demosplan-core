<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\EntryAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use demosplan\DemosPlanCoreBundle\Repository\ReportRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RemoveCustomerCommand extends CoreCommand
{
    private const OPTION_NAME = 'name';

    protected static $defaultName = 'dplan:data:remove-customer';
    protected static $defaultDescription = 'Deletes an existing customer';

    private ProcedureRepository $procedureRepository;
    private CustomerRepository $customerRepository;
    private ReportRepository $reportRepository;
    private QuestionHelper $helper;

    public function __construct(
        ParameterBagInterface $parameterBag,
        CustomerRepository $customerRepository,
        ProcedureRepository $procedureRepository,
        ReportRepository $reportRepository,
        CustomerService $customerService,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->procedureRepository = $procedureRepository;
        $this->customerRepository = $customerRepository;
        $this->reportRepository = $reportRepository;
        $reservedCustomers = $customerService->getReservedCustomerNamesAndSubdomains();
        $this->reservedNames = array_column($reservedCustomers, 0);
        $this->helper = new QuestionHelper();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            self::OPTION_NAME,
            'i',
            InputOption::VALUE_REQUIRED,
            'The name of the customer to be removed. If omitted it will be asked interactively.'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $customerName = $input->getOption(self::OPTION_NAME);
        if (null === $customerName) {
            $customerName = $this->askCustomerName($input, $output);
        }

        try {
            $customer = $this->customerRepository->findOneBy(['name' => $customerName]);
            if (null === $customer) {
                $output->writeln(
                    'Customer with name '.$customerName.' not found.',
                    OutputInterface::VERBOSITY_NORMAL
                );

                return Command::INVALID;
            }

            $this->checkForSingleOrgaCustomerRelation($customer);

            $this->trydeleteOrgasOfCustomer($customer); // reports of customers is missleading
            $this->deleteRelatedBlueprint($customer);
            $this->deleteRelationsToRolesOfUsers($customer);
            $this->deleteRelationsToOrgaTypes($customer);
            $this->deleteRelationsToCounties($customer);
            // do not delete reports which are related to the custer, because this is a misleading relation!
            $this->deleteReportsOfProcedures($customer); // reports of customers is missleading

            $this->deleteCustomer($customer);

            $output->writeln(
                "Customer '$customerName' was successfully removed.",
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln(
                'Something went wrong during customer deletion: '.$e->getMessage(),
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::FAILURE;
        }
    }

    private function askCustomerName(InputInterface $input, OutputInterface $output): string
    {
        $questionName = new Question('Please enter the full name of the customer to remove:', 'default');
        $questionName->setValidator($this->assertExistingCustomer(...));

        return $this->helper->ask($input, $output, $questionName);
    }

    /**
     * @throws \Exception
     */
    private function deleteRelatedBlueprint(Customer $customer): void
    {
        $blueprintOfCustomer = $customer->getDefaultProcedureBlueprint();
        // Detach blueprint form customer to avoid doctrine exception caused by "new" procedure found on customer.
        $customer->setDefaultProcedureBlueprint(null);
        if (null !== $blueprintOfCustomer) {
            $this->procedureRepository->deleteProcedures([$blueprintOfCustomer->getId()]);
            $this->customerRepository->persistAndDelete([$customer], []);
        }
    }

    private function deleteRelationsToRolesOfUsers(Customer $customer): void
    {
        $userRoles = $customer->getUserRoles()->toArray();
        $this->customerRepository->persistAndDelete([], $userRoles);
    }

    /**
     * OrgaStatusInCustomer == relation_customer_orga_orga_type.
     */
    private function deleteRelationsToOrgaTypes(Customer $customer): void
    {
        $orgaStatuses = $customer->getOrgaStatuses()->toArray();

        $this->customerRepository->persistAndDelete([], $orgaStatuses);
    }

    private function deleteRelationsToCounties(Customer $customer): void
    {
        $customerCounties = $customer->getCustomerCounties()->toArray();
        $this->customerRepository->persistAndDelete([], $customerCounties);
    }

    private function deleteReportsOfProcedures(Customer $customer): void
    {
        // fixme:
//        $reports = $this->reportRepository->findBy(['customer' => $customer->getId()]);
//        $this->reportRepository->persistAndDelete([], $reports);
    }

    private function deleteCustomer(Customer $customer): void
    {
        $this->customerRepository->persistAndDelete([], [$customer]);
    }

    public function assertExistingCustomer(mixed $name): string
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Customer name must be a string.');
        }

        if (!in_array($name, $this->reservedNames, true)) {
            throw new EntryAlreadyExistsException('A customer with this name could not be found.');
        }

        return $name;
    }

    /**
     * Only in case of there is only a single Orga related to the cusomter,
     * th.
     */
    private function trydeleteOrgasOfCustomer(Customer $customer)
    {
        // todo
    }

    /**
     * The only case we can proceed with deleting a customer is,
     * if the related organisations of the "customerToDelete", are only related to this single customer!
     */
    private function checkForSingleOrgaCustomerRelation(Customer $customerToDelete)
    {
        // in case of mulitiple customers on one of the related orgas of the $customerToDelete
        // (throw exception and) abort deletion
    }
}
