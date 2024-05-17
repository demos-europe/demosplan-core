<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration\CustomerConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use EFrane\ConsoleAdditions\Batch\Batch;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

use function is_string;

class ContainerInitCommand extends CoreCommand
{
    private const OPTION_CUSTOMER_CONFIG = 'customerConfig';

    protected static $defaultName = 'dplan:container:init';
    protected static $defaultDescription = 'Perform startup tasks that may be used e.g. as an init container in kubernetes setup';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TransactionService $transactionService,
        private readonly UserRepository $userRepository,
        private readonly CustomerService $customerService,
        private readonly UserService $userService,
        ParameterBagInterface $parameterBag,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this
           ->setHelp(
               <<<EOT
Perform startup tasks that may be used e.g. as an init container in kubernetes setup. Usage:
    php bin/<project> dplan:container:init
EOT
           );

        $this->addOption(
            self::OPTION_CUSTOMER_CONFIG,
            'c',
            InputOption::VALUE_REQUIRED,
            'Provide a YAML config file containing properties to be set in the generated customer.'
        );
        $this->addOption(
            'override-database',
            null,
            InputOption::VALUE_NONE,
            'Override existing database'
        );
        $this->addOption(
            'skip-es-populate',
            null,
            InputOption::VALUE_NONE,
            'Skip populating elasticsearch'
        );
    }

    /**
     * Update demosplan.
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->initializeDatabase($input, $output);
            $this->initializeCustomer($input, $output);
            $this->migrateDatabase($output);
            if (!$input->getOption('skip-es-populate')) {
                $this->elasticsearchPopulate($output);
            }
        } catch (Exception) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    protected function initializeDatabase(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('override-database')) {
            $output->writeln('Delete existing database');
            try {
                $this->createDatabase($output);
            } catch (Exception $exception) {
                $output->writeln(
                    "Something went wrong during database override: {$exception->getMessage()}",
                    OutputInterface::VERBOSITY_NORMAL
                );

                throw $exception;
            }
        }

        $connection = $this->entityManager->getConnection();
        try {
            $connection->getDatabase();
        } catch (ConnectionException) {
            try {
                // create database, if it does not exist yet
                $this->createDatabase($output);
            } catch (Exception $exception) {
                $output->writeln(
                    "Something went wrong during database initialization: {$exception->getMessage()}",
                    OutputInterface::VERBOSITY_NORMAL
                );

                throw $exception;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    private function initializeCustomer(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = $this->getCustomerConfig($input);
            if (null === $config) {
                return Command::SUCCESS;
            }

            $customerName = $config[CustomerConfiguration::NAME];
            $customerSubdomain = $config[CustomerConfiguration::SUBDOMAIN];
            $userLogin = $config[CustomerConfiguration::USER_LOGIN] ?? null;

            /** @var Customer $customer */
            /** @var User $user */
            [$customer, $user] = $this->transactionService->executeAndFlushInTransaction(
                function () use (
                    $customerName,
                    $customerSubdomain,
                    $userLogin
                ): array {
                    $customer = $this->customerService->createCustomer($customerName, $customerSubdomain);
                    if (null === $userLogin) {
                        return [$customer, null];
                    }

                    $user = $this->userRepository->getFirstUserByCaseInsensitiveLogin($userLogin);
                    if (null === $user) {
                        $user = $this->userService->createMasterUserForCustomer(
                            $userLogin,
                            $customer
                        );
                    }

                    return [$customer, $user];
                }
            );

            $output->writeln(
                null === $user
                    ? "Customer '{$customer->getName()}' was successfully created."
                    : "Customer '{$customer->getName()}' with user '{$user->getLogin()} was successfully created.",
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::SUCCESS;
        } catch (Exception $exception) {
            $output->writeln(
                "Something went wrong during customer creation via configuration file: {$exception->getMessage()}",
                OutputInterface::VERBOSITY_NORMAL
            );

            throw $exception;
        }
    }

    /**
     * @throws Exception
     */
    protected function elasticsearchPopulate(OutputInterface $output): int
    {
        $output->writeln('populate ES');
        try {
            Batch::create($this->getApplication(), $output)
                ->add('fos:elastica:reset -e prod --no-debug')
                ->add('fos:elastica:populate -e prod --no-debug')
                ->run();
        } catch (Exception $exception) {
            $output->writeln(
                "Something went wrong during elasticsearch populate: {$exception->getMessage()}",
                OutputInterface::VERBOSITY_NORMAL
            );

            throw $exception;
        }

        return Command::SUCCESS;
    }

    /**
     * @throws Exception
     */
    protected function migrateDatabase(OutputInterface $output): int
    {
        try {
            Batch::create($this->getApplication(), $output)
                ->add('dplan:migrate -e prod')
                ->run();
        } catch (Exception $exception) {
            $output->writeln(
                "Something went wrong during database migration: {$exception->getMessage()}",
                OutputInterface::VERBOSITY_NORMAL
            );

            throw $exception;
        }

        return Command::SUCCESS;
    }

    protected function createDatabase(OutputInterface $output): void
    {
        // let exception bubble
        Batch::create($this->getApplication(), $output)
            ->add('dplan:db:init --with-fixtures=ProdData --create-database -e prod')
            ->run();
        $output->writeln('DB created');
    }

    /**
     * Loads, parses and validates the config if it is given as option in the input.
     *
     * @return array{customerName: string, customerSubdomain: string, userLogin: string}|null the
     *                                                                                        loaded config as associative array or `null` if no config path was given
     *
     * @throws Exception if the config path or config content is invalid
     */
    private function getCustomerConfig(InputInterface $input): ?array
    {
        $configPath = $input->getOption(self::OPTION_CUSTOMER_CONFIG);
        if (null === $configPath) {
            return null;
        }

        if (!is_string($configPath)) {
            $type = gettype($configPath);
            throw new InvalidArgumentException("Value of 'config' option must be a string, '$type' given.");
        }

        $config = Yaml::parseFile(DemosPlanPath::getRootPath($configPath));
        $processor = new Processor();

        $reservedCustomers = $this->customerService->getReservedCustomerNamesAndSubdomains();
        $databaseConfiguration = new CustomerConfiguration(
            array_column($reservedCustomers, 0),
            array_column($reservedCustomers, 1)
        );

        return $processor->processConfiguration($databaseConfiguration, [$config]);
    }
}
