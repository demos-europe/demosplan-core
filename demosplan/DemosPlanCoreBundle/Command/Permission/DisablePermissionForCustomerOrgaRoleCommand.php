<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Permission;

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlPermissionService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * dplan:update.
 *
 * Update current project
 */
class DisablePermissionForCustomerOrgaRoleCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:permission:disable:customer-orga-role';
    protected static $defaultDescription = 'Disables a specific permission for a given customer, organization, and role';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly CustomerService $customerService,
        private readonly OrgaRepository $orgaRepository,
        private readonly RoleService $roleService,
        private readonly AccessControlPermissionService $accessControlPermissionService,
        ?string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $customer = $this->askAndConfirm(
            $input,
            $output,
            'Please enter a customer subdomain: ',
            fn ($answer) => $this->getCustomerFromDatabase($answer),
            fn ($result) => 'You have selected: '.$result->getSubdomain().$result->getName().'. Is this correct? (yes/no) '
        );

        $orga = $this->askAndConfirm(
            $input,
            $output,
            'Please enter an organization ID: ',
            fn ($answer) => $this->getOrgaFromDatabase($answer),
            fn ($result) => 'You have selected: '.$result->getName().'. Is this correct? (yes/no) '
        );

        $role = $this->askAndConfirm(
            $input,
            $output,
            'Please enter an Role ID: ',
            fn ($answer) => $this->getRolesFromDatabase($answer),
            fn ($result) => 'You have selected: '.$result->getName().'. Is this correct? (yes/no) '
        );

        // Fetch permissions from the AccessControlPermissionService class
        $permissions = $this->getPermissionsFromAccessControlPermissionService();

        // Prepare choices for the question
        $choices = [];
        foreach ($permissions as $permission) {
            $choices[] = $permission;
        }

        $permissionChoice = $this->askAndConfirmPermission($input, $output);

        // Display the selected options
        $output->writeln('You have selected the following options:');
        $output->writeln('Customer: '.$customer->getName());
        $output->writeln('Organization: '.$orga->getName());
        $output->writeln('Role: '.$role->getName());
        $output->writeln('Permission: '.$permissionChoice);

        // Ask the user to confirm the selected options
        $confirmationQuestion = new ConfirmationQuestion('Are these options correct? (yes/no) ', false);

        $helper = $this->getHelper('question');

        if (!$helper->ask($input, $output, $confirmationQuestion)) {
            $output->writeln('The command has ended.');

            return Command::FAILURE;
        }

        $output->writeln('You have confirmed all the options.');

        $this->enablePermissionForAllExceptOrga($permissionChoice, $orga, $customer, $role);

        // Continue with your logic here...

        return Command::SUCCESS;
    }

    private function askAndConfirm(InputInterface $input, OutputInterface $output, string $questionText, callable $fetch, callable $confirm): mixed
    {
        $helper = $this->getHelper('question');
        while (true) {
            $question = new Question($questionText);
            $answer = $helper->ask($input, $output, $question);

            $result = $fetch($answer);

            if (null !== $result) {
                $confirmationQuestion = new ConfirmationQuestion($confirm($result), false);

                if (!$helper->ask($input, $output, $confirmationQuestion)) {
                    $output->writeln('Please enter the information again.');
                    continue;
                }

                $output->writeln('You have confirmed: '.$result->getName());

                return $result;
            } else {
                $output->writeln('No valid input found. Please try again.');
            }
        }
    }

    private function askAndConfirmPermission(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        // Fetch permissions from the AccessControlPermissionService class
        $permissions = $this->getPermissionsFromAccessControlPermissionService();

        // Prepare choices for the question
        $choices = [];
        foreach ($permissions as $permission) {
            $choices[] = $permission;
        }

        // Loop for permission
        while (true) {
            // Ask the user to select a permission
            $question = new ChoiceQuestion('Please select a permission', $choices);
            $permissionChoice = $helper->ask($input, $output, $question);

            // Ask the user to confirm the selected permission
            $confirmationQuestion = new ConfirmationQuestion('You have selected: '.$permissionChoice.'. Is this correct? (yes/no) ', false);

            if (!$helper->ask($input, $output, $confirmationQuestion)) {
                $output->writeln('Please select the permission again.');
                continue;
            }

            $output->writeln('You have confirmed: '.$permissionChoice);

            return $permissionChoice;
        }
    }

    private function getCustomerFromDatabase($customerSubdomain): ?CustomerInterface
    {
        try {
            return $this->customerService->findCustomerBySubdomain($customerSubdomain);
        } catch (CustomerNotFoundException $e) {
            return null;
        }
    }

    private function getOrgaFromDatabase($orgaId): ?OrgaInterface
    {
        return $this->orgaRepository->get($orgaId);
    }

    private function getRolesFromDatabase($roleId): ?RoleInterface
    {
        // Replace this with your actual database query
        // This is just a placeholder
        return $this->roleService->getRole($roleId);
    }

    private function getPermissionsFromAccessControlPermissionService(): array
    {
        $reflection = new ReflectionClass(AccessControlPermissionService::class);

        return array_keys($reflection->getConstants());
    }

    private function enablePermissionForAllExceptOrga(mixed $permissionChoice, OrgaInterface $excludedOrga, CustomerInterface $customer, RoleInterface $role)
    {
        $constantValue = $this->getConstantValueByName($permissionChoice);

        $this->accessControlPermissionService->enablePermissionForAllExceptOrga($constantValue, $customer, $excludedOrga, $role);
    }

    private function getConstantValueByName($constantName): string
    {
        $className = AccessControlPermissionService::class;
        $constantFullName = $className.'::'.$constantName;
        if (defined($constantFullName)) {
            return constant($constantFullName);
        } else {
            throw new Exception("Constant {$constantFullName} does not exist");
        }
    }
}
