<?php

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
use demosplan\DemosPlanCoreBundle\Logic\Customer\CustomerDeleter;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DeleteCustomerCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:customer:delete';
    protected static $defaultDescription =
        'Deletes a customer including all related content like procedure, orgaTypes, statements, tags, News, etc.';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly CustomerRepository $customerRepository,
        private readonly CustomerDeleter $customerDeleter,
        private readonly QuestionHelper $helper = new QuestionHelper(),
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_OPTIONAL,
            'Initiates a dry run with verbose output to see what would happen.',
            false
        );

        $this->addOption(
            'without-repopulate',
            'wrp',
            InputOption::VALUE_OPTIONAL,
            'Ignores repopulating the ES. This should only be used for debugging purposes!',
            false
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output = new SymfonyStyle($input, $output);
            $isDryRun = $input->getOption('dry-run');
            $isDryRun = null === $isDryRun || is_string($isDryRun);
            $withoutRepopulate = $input->getOption('without-repopulate');
            $withoutRepopulate = null === $withoutRepopulate || is_string($withoutRepopulate);
            // get the available customers
            $customerId = $this->askCustomerToDelete($input, $output);
            $output->info('Customer id to delete: '.$customerId);
            if ($isDryRun) {
                $output->info('run with option dry-run');
            }
            if ($withoutRepopulate) {
                $output->info('run without-es-repopulate');
            }

            $this->customerDeleter->deleteCustomer($customerId);
            if (!$withoutRepopulate && !$isDryRun) {
                $this->repopulateElasticsearch($output);
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            // Print Exception
            $output->writeln(
                'Something went wrong with the exception: '.$e->getMessage(),
                OutputInterface::VERBOSITY_VERBOSE
            );

            return Command::FAILURE;
        }
    }

    private function askCustomerToDelete(InputInterface $input, OutputInterface $output): string
    {
        $availableCustomers = $this->customerRepository->findAll();
        $availableCustomerIds = array_map(
            static fn (Customer $customer): string => $customer->getSubdomain().' id: '.$customer->getId(),
            $availableCustomers
        );
        $questionDepartment = new ChoiceQuestion('Please select a Customer: ', $availableCustomerIds);
        $answer = $this->helper->ask($input, $output, $questionDepartment);

        $chosenCustomer = array_filter(
            $availableCustomers,
            static fn (Customer $customer): bool => $customer->getSubdomain().' id: '.$customer->getId() === $answer
        );
        $chosenCustomer = reset($chosenCustomer);
        if (false === $chosenCustomer instanceof Customer) {
            throw new RuntimeException('Given customer is not available.');
        }

        return $chosenCustomer->getId();
    }

    /**
     * @throws Exception
     */
    private function repopulateElasticsearch(OutputInterface $output): void
    {
        $env = $this->parameterBag->get('kernel.environment');
        $output->writeln("Repopulating ES with env: $env");

        $repopulateEsCommand = 'dev' === $env ? 'dplan:elasticsearch:populate' : 'dplan:elasticsearch:populate -e prod --no-debug';
        Batch::create($this->getApplication(), $output)
            ->add($repopulateEsCommand)
            ->run();
    }
}
