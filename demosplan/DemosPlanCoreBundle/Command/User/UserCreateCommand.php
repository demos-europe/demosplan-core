<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\User;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Command\Helpers\Helpers;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UserCreateCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:user:create';
    protected static $defaultDescription = 'Creates a new user with customer, orga, department and roles';
    /**
     * @var mixed|QuestionHelper
     */
    private $helper;

    public function __construct(
        private readonly Helpers $helpers,
        private readonly OrgaRepository $orgaRepository,
        ParameterBagInterface $parameterBag,
        private readonly UserRepository $userRepository,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->helper = new QuestionHelper();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $firstName = $this->askFirstname($input, $output);
        $lastName = $this->askLastname($input, $output);
        $email = $this->askEmail($input, $output);
        $password = $this->askPassword($input, $output);
        $customer = $this->helpers->askCustomer($input, $output);
        $orga = $this->askOrga($customer->getId(), $input, $output);
        $department = $this->askDepartment($orga->getId(), $input, $output);
        $roles = $this->helpers->askRoles($input, $output, $this->parameterBag->get('roles_allowed'));

        // Create user
        $data = [
            'firstname'      => $firstName,
            'lastname'       => $lastName,
            'email'          => $email,
            'login'          => $email,
            'password'       => hash('sha512', $password),
            'customer'       => $customer,
            'organisation'   => $orga,
            'department'     => $department,
            'roles'          => $roles,
        ];

        try {
            $this->userRepository->add($data);
            $output->writeln(
                'User successfully created!',
                OutputInterface::VERBOSITY_VERBOSE
            );

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

    private function askFirstname(InputInterface $input, OutputInterface $output): string
    {
        $questionFirstname = new Question('Please enter the firstname of the user (Defaults to Bob): ', 'Bob');

        return $this->helper->ask($input, $output, $questionFirstname);
    }

    private function askLastname(InputInterface $input, OutputInterface $output): string
    {
        $questionLastname = new Question('Please enter the lastname of the user (Defaults to Bobson): ', 'Bobson');

        return $this->helper->ask($input, $output, $questionLastname);
    }

    private function askEmail(InputInterface $input, OutputInterface $output): string
    {
        $questionEmail = new Question('Please enter the email of the user (Defaults to bobson@demos.de): ', 'bobson@demos.de');
        $questionEmail->setValidator(function ($answer) {
            $existingUser = $this->userRepository->findOneBy(['login' => $answer]);
            if (null !== $existingUser) {
                throw new RuntimeException('This email is already used as a login, please choose another one.');
            }

            return $answer;
        });

        return $this->helper->ask($input, $output, $questionEmail);
    }

    private function askPassword(InputInterface $input, OutputInterface $output): string
    {
        $questionPassword = new Question('Please enter the password of the user (Defaults to Advanced_12345): ', 'Advanced_12345');

        return $this->helper->ask($input, $output, $questionPassword);
    }

    private function askOrga(string $customerId, InputInterface $input, OutputInterface $output): Orga
    {
        $questionOrga = new Question('Please enter the id of the users organisation: ');
        $questionOrga->setValidator(function ($answer) use ($customerId) {
            $orga = $this->orgaRepository->get($answer);
            if (null === $orga) {
                throw new RuntimeException('There is no orga with this id');
            }
            // Check if orga is active in customer
            $customers = $orga->getCustomers();
            $filteredCustomers = $customers->filter(fn (Customer $customer) => $customerId === $customer->getId());
            $isOrgaMissingInCustomer = $filteredCustomers->isEmpty();
            if ($isOrgaMissingInCustomer) {
                throw new RuntimeException('Given orga is not active in the chosen customer.');
            }

            return $orga;
        });

        return $this->helper->ask($input, $output, $questionOrga);
    }

    private function askDepartment(string $orgaId, InputInterface $input, OutputInterface $output): Department
    {
        $availableDepartments = $this->orgaRepository->get($orgaId)->getDepartments();
        $departmentSelection = $availableDepartments->map(fn (Department $department) => $department->getName())->toArray();
        $questionDepartment = new ChoiceQuestion('Please select a department: ', $departmentSelection);
        $answer = $this->helper->ask($input, $output, $questionDepartment);

        return $availableDepartments->first(fn (Department $department) => $answer === $department->getName());
    }
}
