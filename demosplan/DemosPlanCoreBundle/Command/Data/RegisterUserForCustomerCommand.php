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
use demosplan\DemosPlanCoreBundle\Command\Helpers\Helpers;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaTypeRepository;
use demosplan\DemosPlanCoreBundle\Repository\RoleRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RegisterUserForCustomerCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:data:register-user-for-customer';
    protected static $defaultDescription = 'Registers an existing user to an existing customer';
    /**
     * @var QuestionHelper
     */
    protected $helper;

    public function __construct(
        private readonly Helpers $helpers,
        private readonly OrgaRepository $orgaRepository,
        private readonly OrgaTypeRepository $orgaTypeRepository,
        ParameterBagInterface $parameterBag,
        private readonly RoleRepository $roleRepository,
        private readonly UserRepository $userRepository,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
        $this->helper = new QuestionHelper();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userToRegister = $this->askUserLogin($input, $output);
        if (null === $userToRegister) {
            $output->writeln('No user for Login found', OutputInterface::VERBOSITY_NORMAL);

            return Command::FAILURE;
        }
        $customer = $this->helpers->askCustomer($input, $output);
        $roles = $this->helpers->askRoles($input, $output, $this->parameterBag->get('roles_allowed'));

        try {
            // add user to customer
            $userToRegister->setDplanroles($roles, $customer);
            $this->userRepository->updateObject($userToRegister);

            // add OrgaType to customer
            $orga = $userToRegister->getOrga();
            $orgaTypes = $this->getOrgaTypesByRoles($roles);
            foreach ($orgaTypes as $orgaType) {
                $orga->addCustomerAndOrgaType($customer, $orgaType, OrgaStatusInCustomer::STATUS_ACCEPTED);
            }
            $this->orgaRepository->updateObject($orga);

            $output->writeln(
                'User successfully registered for customer!',
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::SUCCESS;
        } catch (Exception $e) {
            // Print Exception
            $output->writeln(
                'Something went wrong: '.$e->getMessage(),
                OutputInterface::VERBOSITY_NORMAL
            );

            return Command::FAILURE;
        }
    }

    private function askUserLogin(InputInterface $input, OutputInterface $output): ?User
    {
        $questionUser = new Question('Please enter the login of the user to be registered: ');
        $questionUser->setValidator(fn ($answer) => $this->userRepository->findOneBy(['login' => $answer]));

        return $this->helper->ask($input, $output, $questionUser);
    }

    /**
     * @param array<int, Role> $roles
     *
     * @return array<int, OrgaType|null>
     */
    private function getOrgaTypesByRoles(array $roles): array
    {
        $orgaTypeStrings = array_map($this->roleRepository->getOrgaTypeString(...), $roles);

        $orgaTypes = [];
        foreach (array_unique($orgaTypeStrings) as $orgaTypeString) {
            $orgaTypes[] = $this->orgaTypeRepository->findOneBy(['name' => $orgaTypeString]);
        }

        return $orgaTypes;
    }
}
