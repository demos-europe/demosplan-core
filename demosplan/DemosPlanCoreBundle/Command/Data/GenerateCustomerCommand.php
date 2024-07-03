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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\EntryAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function in_array;
use function is_string;

class GenerateCustomerCommand extends CoreCommand
{
    private const OPTION_NAME = 'name';
    private const OPTION_SUBDOMAIN = 'subdomain';

    protected static $defaultName = 'dplan:data:generate-customer';
    protected static $defaultDescription = 'Creates a new customer';

    protected QuestionHelper $helper;

    /**
     * @var list<string>
     */
    private readonly array $reservedNames;

    /**
     * @var list<string>
     */
    private readonly array $reservedSubdomains;

    public function __construct(
        private readonly CustomerService $customerService,
        private readonly EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        private readonly RoleRepository $roleRepository,
        private readonly UserService $userService,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->helper = new QuestionHelper();
        $reservedCustomers = $this->customerService->getReservedCustomerNamesAndSubdomains();
        $this->reservedNames = array_column($reservedCustomers, 0);
        $this->reservedSubdomains = array_column($reservedCustomers, 1);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            self::OPTION_NAME,
            'i',
            InputOption::VALUE_REQUIRED,
            'The name of the customer to be created. If omitted it will be asked interactively.'
        );
        $this->addOption(
            self::OPTION_SUBDOMAIN,
            's',
            InputOption::VALUE_REQUIRED,
            'The subdomain of the customer to be created. If omitted it will be asked interactively.'
        );
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getOption(self::OPTION_NAME);
        if (null === $name) {
            $name = $this->askCustomerName($input, $output);
        }

        $subdomain = $input->getOption(self::OPTION_SUBDOMAIN);
        if (null === $subdomain) {
            $subdomain = $this->askSubdomain($input, $output);
        }

        try {
            // create customer
            $customer = $this->customerService->createCustomer($name, $subdomain);
            $this->registerDefaultUsers($customer);
            $this->entityManager->flush();

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
        $questionName->setValidator($this->assertFreeName(...));

        return $this->helper->ask($input, $output, $questionName);
    }

    private function askSubdomain(InputInterface $input, OutputInterface $output): string
    {
        $questionSubdomain = new Question('Please enter the Subdomain of the customer:', 'default');
        $questionSubdomain->setValidator($this->assertFreeSubdomain(...));

        return $this->helper->ask($input, $output, $questionSubdomain);
    }

    public function assertFreeName(mixed $name): string
    {
        if (!is_string($name)) {
            throw new InvalidArgumentException('Customer name must be a string.');
        }

        if (in_array($name, $this->reservedNames, true)) {
            throw new EntryAlreadyExistsException('This name is already used as a customer, please choose another one.');
        }

        return $name;
    }

    public function assertFreeSubdomain(mixed $subdomain): string
    {
        if (!is_string($subdomain)) {
            throw new InvalidArgumentException('Customer subdomain must be a string.');
        }

        if (in_array($subdomain, $this->reservedSubdomains, true)) {
            throw new EntryAlreadyExistsException('This subdomain is already used as a customer, please choose another one.');
        }

        return $subdomain;
    }

    private function registerDefaultUsers(Customer $customer): void
    {
        // register AiApiUser and AnonymousUser
        $this->registerUser($customer, AiApiUser::AI_API_USER_LOGIN, RoleInterface::API_AI_COMMUNICATOR);
        $this->registerUser($customer, UserInterface::ANONYMOUS_USER_LOGIN, RoleInterface::CITIZEN);
    }

    private function registerUser(Customer $customer, string $login, string $roleString): void
    {
        $user = $this->userService->findDistinctUserByEmailOrLogin($login);
        if ($user instanceof User) {
            // add user to customer
            $user->setDplanroles(
                $this->roleRepository->getUserRolesByCodes([$roleString]),
                $customer
            );
            $this->userService->updateUserObject($user);
        }
    }
}
