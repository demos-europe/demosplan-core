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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * This Command is used to disable a specific permission for a given customer, organization, and role.
 */
class DisablePermissionForCustomerOrgaRoleCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:permission:disable:customer-orga-role';
    protected static $defaultDescription = 'Disables a specific permission for a given customer, organization, and role';

    protected static $CHOICE_QUESTION = 'CHOICE_QUESTION';
    protected static $SIMPLE_QUESTION = 'SIMPLE_QUESTION';

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

    public function configure(): void
    {
        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Initiates a dry run with verbose output to see what would happen.'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);

        $customer = $this->askAndConfirm(
            $input,
            $output,
            'Please enter a customer subdomain: ',
            fn ($answer) => $this->getCustomerFromDatabase($answer),
            fn ($result) => 'You have selected: '.$result->getSubdomain().$result->getName().'. Is this correct? (yes/no) ',
            self::$SIMPLE_QUESTION
        );

        $orgas = $this->askAndConfirm(
            $input,
            $output,
            'Please enter organization IDs (comma separated): ',
            fn ($answer) => $this->getOrgasFromDatabase($answer),
            fn ($result) => 'You have selected: '.implode(', ', array_map(fn($orga) => $orga->getName(), $result)).'. Is this correct? (yes/no) ',
            self::$SIMPLE_QUESTION
        );

        $role = $this->askAndConfirm(
            $input,
            $output,
            'Please enter an Role ID: ',
            fn ($answer) => $this->getRolesFromDatabase($answer),
            fn ($result) => 'You have selected: '.$result->getName().'. Is this correct? (yes/no) ',
            self::$SIMPLE_QUESTION
        );

        $permissionChoice = $this->askAndConfirm(
            $input,
            $output,
            'Please select a permission: ',
            fn ($answer) => $this->getConstant($answer),
            fn ($result) => 'You have selected: '.$result.'. Is this correct? (yes/no) ',
            self::$CHOICE_QUESTION,
            $this->getPermissions()
        );

        // Display the selected options
        $output->writeln('You have selected the following options:');
        $output->writeln('Customer: '.$customer->getName());
        // Loop through the organizations and display their names
        foreach ($orgas as $orga) {
            $output->writeln('Organization: '.$orga->getName());
        }
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

        $dryRun = $input->getOption('dry-run');

        $updatedOrgas = $this->enablePermissionForAllExceptOrgas($permissionChoice, $orgas, $customer, $role, $dryRun);

        $output->writeln('Impacted orgas are:');
        foreach ($updatedOrgas as $orga) {
            $output->writeln('Orga ID: '.$orga->getId());
            $output->writeln('Orga Name: '.$orga->getName());
        }

        return Command::SUCCESS;
    }

    private function askAndConfirm(InputInterface $input, OutputInterface $output, string $questionText, callable $fetchEntityBasedOnInsertedId, callable $formatConfirmationMessage, string $questionType, ?array $choices = null): CustomerInterface|OrgaInterface|RoleInterface|array|string
    {
        $helper = $this->getHelper('question');
        while (true) {
            if (self::$SIMPLE_QUESTION === $questionType) {
                $question = new Question($questionText);
                $answer = $helper->ask($input, $output, $question);
            } else {
                $question = new ChoiceQuestion('Please select a permission', $choices);
                $answer = $helper->ask($input, $output, $question);
            }

            $result = $fetchEntityBasedOnInsertedId($answer);

            if (null !== $result) {
                $confirmationQuestion = new ConfirmationQuestion($formatConfirmationMessage($result), false);

                if (!$helper->ask($input, $output, $confirmationQuestion)) {
                    $output->writeln('Please enter the information again.');
                    continue;
                }

                return $result;
            } else {
                $output->writeln('No valid input found. Please try again.');
            }
        }
    }

    private function getPermissions(): array
    {
        // Fetch permissions from the AccessControlPermissionService class
        $permissions = $this->getPermissionsFromAccessControlPermissionService();

        // Prepare choices for the question
        $choices = [];
        foreach ($permissions as $permission) {
            $choices[] = $permission;
        }

        return $choices;
    }

    private function getCustomerFromDatabase($customerSubdomain): ?CustomerInterface
    {
        try {
            return $this->customerService->findCustomerBySubdomain($customerSubdomain);
        } catch (CustomerNotFoundException $e) {
            return null;
        }
    }

    private function getOrgasFromDatabase($orgaIds): array
    {
        $orgaIds = explode(',', $orgaIds);
        $orgas = [];
        foreach ($orgaIds as $orgaId) {
            $orga = $this->orgaRepository->get(trim($orgaId));
            if ($orga !== null) {
                $orgas[] = $orga;
            }
        }
        return $orgas;
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

    private function enablePermissionForAllExceptOrgas(mixed $permissionChoice, array $excludedOrgas, CustomerInterface $customer, RoleInterface $role, bool $dryRun): array
    {
        $constantValue = $this->getConstantValueByName($permissionChoice);
        return $this->accessControlPermissionService->enablePermissionForAllExceptOrga($constantValue, $customer, $excludedOrgas, $role, $dryRun);
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

    private function getConstant(string $constantName): ?string
    {
        $reflection = new ReflectionClass(AccessControlPermissionService::class);
        $constants = $reflection->getConstants();

        if (array_key_exists($constantName, $constants)) {
            return $constantName;
        }

        return null;
    }
}
