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
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
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
        string $name = null
    ) {
        parent::__construct($parameterBag, $name);
    }
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        while (true) {
            // Ask the user to enter a customer ID
            $question = new Question('Please enter a customer subdomain: ');
            $customerSubdomain = $helper->ask($input, $output, $question);

            // Fetch the customer from the database
            $customer = $this->getCustomerFromDatabase($customerSubdomain);

            if (null !== $customer) {
                // Ask the user to confirm the selected customer
                $confirmationQuestion = new ConfirmationQuestion('You have selected: ' . $customer->getSubdomain() . $customer->getName() . '. Is this correct? (yes/no) ', false);

                if (!$helper->ask($input, $output, $confirmationQuestion)) {
                    $output->writeln('Please enter the customer subdomain again.');
                    continue;
                }

                $output->writeln('You have confirmed: ' . $customer->getName());
                break;
            } else {
                $output->writeln('No customer found with the provided subdomain. Please try again.');
            }
        }

        // Loop for organization
        while (true) {
            // Ask the user to enter an organization ID
            $question = new Question('Please enter an organization ID: ');
            $orgaId = $helper->ask($input, $output, $question);

            // Fetch the organization from the database
            $orga = $this->getOrgaFromDatabase($orgaId);

            if (null !== $orga) {
                // Ask the user to confirm the selected organization
                $confirmationQuestion = new ConfirmationQuestion('You have selected: ' . $orga->getName() . '. Is this correct? (yes/no) ', false);

                if (!$helper->ask($input, $output, $confirmationQuestion)) {
                    $output->writeln('Please enter the organization ID again.');
                    continue;
                }

                $output->writeln('You have confirmed: ' . $orga->getName());
                break;
            } else {
                $output->writeln('No organization found with the provided ID. Please try again.');
            }
        }



        // Fetch roles from the database
        $roles = $this->getRolesFromDatabase();

        // Prepare choices for the question
        $choices = [];
        foreach ($roles as $role) {
            $choices[] = $role->getName();
        }

        // Loop for role
        while (true) {
            // Ask the user to select a role
            $question = new ChoiceQuestion('Please select a role', $choices);
            $roleChoice = $helper->ask($input, $output, $question);

            // Ask the user to confirm the selected role
            $confirmationQuestion = new ConfirmationQuestion('You have selected: ' . $roleChoice . '. Is this correct? (yes/no) ', false);

            if (!$helper->ask($input, $output, $confirmationQuestion)) {
                $output->writeln('Please select the role again.');
                continue;
            }

            $output->writeln('You have confirmed: ' . $roleChoice);
            break;
        }


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
            $confirmationQuestion = new ConfirmationQuestion('You have selected: ' . $permissionChoice . '. Is this correct? (yes/no) ', false);

            if (!$helper->ask($input, $output, $confirmationQuestion)) {
                $output->writeln('Please select the permission again.');
                continue;
            }

            $output->writeln('You have confirmed: ' . $permissionChoice);
            break;
        }



        // Display the selected options
        $output->writeln('You have selected the following options:');
        $output->writeln('Customer: ' . $customer->getName());
        $output->writeln('Organization: ' . $orga->getName());
        $output->writeln('Role: ' . $roleChoice);
        $output->writeln('Permission: ' . $permissionChoice);

        // Ask the user to confirm the selected options
        $confirmationQuestion = new ConfirmationQuestion('Are these options correct? (yes/no) ', false);

        if (!$helper->ask($input, $output, $confirmationQuestion)) {
            $output->writeln('The command has ended.');
            return Command::FAILURE;
        }

        $output->writeln('You have confirmed all the options.');

        // Continue with your logic here...

        return Command::SUCCESS;



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

    private function getRolesFromDatabase(): array
    {
        // Replace this with your actual database query
        // This is just a placeholder
        return $this->roleService->getRoles();
    }

    private function getPermissionsFromAccessControlPermissionService(): array
    {
        $reflection = new \ReflectionClass(AccessControlPermissionService::class);
        return $reflection->getConstants();
    }

}
