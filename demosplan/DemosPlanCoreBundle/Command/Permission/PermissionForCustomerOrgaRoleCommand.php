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
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\RoleNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleService;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function collect;
use function in_array;

/**
 * This Command is the parent class for enable and disable permission commands.
 */
abstract class PermissionForCustomerOrgaRoleCommand extends CoreCommand
{
    public function configure(): void
    {
        $this->addArgument(
            'customerIds',
            InputArgument::OPTIONAL,
            'The Ids of the customer you want to adjust the permission, comma separated. If omitted, an interactive selection is shown.'
        );

        $this->addArgument(
            'permission',
            InputArgument::OPTIONAL,
            'The name of the permission to be adjusted. If omitted, an interactive selection is shown.'
        );

        $this->addArgument(
            'orgaId',
            InputArgument::OPTIONAL,
            'Optional organization ID to restrict changes to a specific organization. If not provided, affects all organizations in the customer.'
        );

        $this->addOption(
            'roles',
            'r',
            InputOption::VALUE_REQUIRED,
            'The Ids of the role you want to adjust the permission, comma separated. If omitted, an interactive selection of project-allowed roles is shown.'
        );

        $this->addOption(
            'dry-run',
            '',
            InputOption::VALUE_NONE,
            'Initiates a dry run with verbose output to see what would happen.'
        );
    }

    public function __construct(
        ParameterBagInterface $parameterBag,
        protected readonly CustomerService $customerService,
        protected readonly RoleService $roleService,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function displayOutcome(OutputInterface $output, $dryRun, array $updatedOrgas, CustomerInterface $customerChoice, RoleInterface $roleChoice, string $action): void
    {
        $output->writeln('******************************************************');
        $output->writeln($dryRun ? 'This is a dry run. No changes have been made to the database.' : 'Changes have been applied to the database.');
        $output->writeln('******************************************************');
        $output->writeln('Permission has been '.$action.' for '.count($updatedOrgas).' orgas');
        $output->writeln('Permission has been '.$action.' for mentioned orgas on:');
        $output->writeln('Customer '.$customerChoice->getId().' '.$customerChoice->getName());
        $output->writeln('Role '.$roleChoice->getId().' '.$roleChoice->getName());
    }

    /**
     * @throws RoleNotFoundException
     * @throws CustomerNotFoundException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $customerIdsString = $input->getArgument('customerIds');
        $roleIdsOption = $input->getOption('roles');
        $permissionName = $input->getArgument('permission');
        $orgaId = $input->getArgument('orgaId');
        $dryRun = $input->getOption('dry-run');

        $customers = null !== $customerIdsString
            ? $this->resolveCustomersByIds(explode(',', (string) $customerIdsString))
            : $this->askForCustomers($input, $output);

        $permissionChoice = null !== $permissionName
            ? $this->getConstantValueByName($permissionName)
            : $this->askForPermission($input, $output);

        $roles = null !== $roleIdsOption
            ? $this->resolveRolesByIds(explode(',', (string) $roleIdsOption))
            : $this->askForProjectRoles($input, $output);

        foreach ($roles as $roleChoice) {
            foreach ($customers as $customerChoice) {

                $updatedOrgas = $this->doExecuteAction($permissionChoice, $customerChoice, $roleChoice, $dryRun, $orgaId ? trim((string) $orgaId) : null);

                $this->displayUpdatedOrgas($output, $updatedOrgas);

                $this->displayOutcome($output, $dryRun, $updatedOrgas, $customerChoice, $roleChoice, $this->getActionName());
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int, string> $customerIds
     *
     * @return array<int, CustomerInterface>
     *
     * @throws CustomerNotFoundException
     */
    private function resolveCustomersByIds(array $customerIds): array
    {
        $customers = [];
        foreach ($customerIds as $customerId) {
            $customers[] = $this->customerService->findCustomerById(trim($customerId));
        }

        return $customers;
    }

    /**
     * @return array<int, CustomerInterface>
     */
    private function askForCustomers(InputInterface $input, OutputInterface $output): array
    {
        $allCustomers = collect($this->customerService->findAll());
        $choices = $allCustomers
            ->mapWithKeys(static fn (CustomerInterface $customer) => [$customer->getSubdomain() => $customer->getName().' ('.$customer->getSubdomain().')'])
            ->sort()
            ->all();

        $question = new ChoiceQuestion(
            'Select customers (comma-separated for multiple, e.g. 0,1,2):',
            $choices,
        );
        $question->setMultiselect(true);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $selectedSubdomains = $helper->ask($input, $output, $question);

        return $allCustomers
            ->filter(static fn (CustomerInterface $customer) => in_array($customer->getSubdomain(), $selectedSubdomains, true))
            ->values()
            ->all();
    }

    private function askForPermission(InputInterface $input, OutputInterface $output): string
    {
        $choices = [
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
        ];

        $question = new ChoiceQuestion('Select a permission:', $choices);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        return $helper->ask($input, $output, $question);
    }

    /**
     * @param array<int, string> $roleIds
     *
     * @return array<int, RoleInterface>
     *
     * @throws RoleNotFoundException
     */
    private function resolveRolesByIds(array $roleIds): array
    {
        $roles = [];
        foreach ($roleIds as $roleId) {
            $role = $this->roleService->getRole(trim($roleId));
            if (null === $role) {
                throw new RoleNotFoundException('Role not found: '.$roleId);
            }
            $roles[] = $role;
        }

        return $roles;
    }

    /**
     * @return array<int, RoleInterface>
     */
    private function askForProjectRoles(InputInterface $input, OutputInterface $output): array
    {
        /** @var array<int, string> $rolesAllowed */
        $rolesAllowed = $this->parameterBag->get('roles_allowed');

        $allRoles = $this->roleService->getUserRolesByCodes($rolesAllowed) ?? [];
        $rolesSelection = collect($allRoles)
            ->mapWithKeys(static fn (RoleInterface $role) => [$role->getCode() => $role->getName().' ('.$role->getCode().')'])
            ->all();

        if ([] === $rolesSelection) {
            throw new InvalidArgumentException('No allowed roles found for this project. Check the roles_allowed parameter.');
        }

        $question = new ChoiceQuestion(
            'Select roles (comma-separated for multiple, e.g. 0,1,2):',
            $rolesSelection,
        );
        $question->setMultiselect(true);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $selectedCodes = $helper->ask($input, $output, $question);

        return collect($allRoles)
            ->filter(static fn (RoleInterface $role) => in_array($role->getCode(), $selectedCodes, true))
            ->values()
            ->all();
    }

    protected function displayUpdatedOrgas(OutputInterface $output, array $updatedOrgas): void
    {
        foreach ($updatedOrgas as $orga) {
            $output->writeln('Orga ID: '.$orga->getId());
            $output->writeln('Orga Name: '.$orga->getName());
        }
    }

    protected function getConstantValueByName(string $constantName): string
    {
        $className = AccessControlService::class;
        $constantFullName = $className.'::'.$constantName;
        if (defined($constantFullName)) {
            return constant($constantFullName);
        }

        throw new InvalidArgumentException('Permission does not exist');
    }

    abstract protected function doExecuteAction(string $permissionChoice, CustomerInterface $customerChoice, RoleInterface $roleChoice, bool $dryRun, ?string $orgaId = null): array;

    abstract protected function getActionName(): string;
}
