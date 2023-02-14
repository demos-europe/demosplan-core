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
use demosplan\DemosPlanCoreBundle\DependencyInjection\Configuration\CustomerConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Exception\EntryAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanUserBundle\Repository\CustomerRepository;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Yaml\Yaml;
use function in_array;
use function is_string;

class GenerateCustomerCommand extends CoreCommand
{
    private const CONFIG_OPTION = 'config';

    protected static $defaultName = 'dplan:data:generate-customer';
    protected static $defaultDescription = 'Creates a new customer';

    protected QuestionHelper $helper;

    /**
     * @var list<string>
     */
    private array $reservedNames;

    /**
     * @var list<string>
     */
    private array $reservedSubdomains;

    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly ValidatorInterface $validator,
        ParameterBagInterface $parameterBag,
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->helper = new QuestionHelper();

        $existingCustomers = $customerRepository->findAll();

        $this->reservedNames = array_map(
            static fn (Customer $customer): string => $customer->getName(),
            $existingCustomers
        );
        $this->reservedSubdomains = array_map(
            static fn (Customer $customer): string => $customer->getSubdomain(),
            $existingCustomers
        );
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addUsage('foobar'); // FIXME

        $this->addOption(
            self::CONFIG_OPTION,
            'c',
            InputOption::VALUE_REQUIRED,
            'Provide a YAML config file containing properties to be set in the generated customer. If a config is given it must contain the required parameters (\'name\' and \'subdomain\'). If no config is given the parameters will be asked interactively.'
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $config = $this->getConfig($input);
        if (null === $config) {
            $name = $this->askCustomerName($input, $output);
            $subdomain = $this->askSubdomain($input, $output);
        } else {
            $name = $config['name'];
            $subdomain = $config['subdomain'];
        }

        $customer = new Customer($name, $subdomain);
        $violations = $this->validator->validate($customer);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        try {
            // create customer
            $this->customerRepository->updateObject($customer);

            $output->writeln(
                "Customer '$name' was successfully created.",
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
        $questionName = new Question('Please enter the full name of the customer:', 'default');

        $questionName->setValidator(function ($answer) {
            if (in_array($answer, $this->reservedNames, true)) {
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
            if (in_array($answer, $this->reservedSubdomains, true)) {
                throw new EntryAlreadyExistsException('This subdomain is already used as a customer, please choose another one.');
            }

            return $answer;
        });

        return $this->helper->ask($input, $output, $questionSubdomain);
    }

    /**
     * Loads, parses and validates the config if it is given as option in the input.
     *
     * @return array<string, mixed>|null the loaded config as associative array or `null` if no config path was given
     *
     * @throws Exception if the config path or config content is invalid
     */
    private function getConfig(InputInterface $input): ?array
    {
        if (!$input->hasOption(self::CONFIG_OPTION)) {
            return null;
        }

        $configPath = $input->getOption(self::CONFIG_OPTION);
        if (!is_string($configPath)) {
            $type = gettype($configPath);
            throw new InvalidArgumentException("Value of 'config' option must be a string, '$type' given.");
        }

        $config = Yaml::parseFile(DemosPlanPath::getRootPath($configPath));
        $processor = new Processor();
        $databaseConfiguration = new CustomerConfiguration(
            $this->reservedNames,
            $this->reservedSubdomains
        );

        return $processor->processConfiguration(
            $databaseConfiguration,
            [$config]
        );
    }
}
