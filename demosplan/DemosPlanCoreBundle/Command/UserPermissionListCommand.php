<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'dplan:user:permission:list', description: 'List all user-specific permissions for a user')]
class UserPermissionListCommand extends CoreCommand
{
    public function __construct(
        private readonly UserAccessControlService $userAccessControlService,
        private readonly UserRepository $userRepository,
        ParameterBagInterface $parameterBag,
    ) {
        parent::__construct($parameterBag);
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command lists all user-specific permissions that have been granted to a specific user.')
            ->addArgument('user-id', InputArgument::REQUIRED, 'User ID (UUID)')
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format (table, json)',
                'table'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userId = $input->getArgument('user-id');
        $format = $input->getOption('format');

        try {
            // Validate output format
            if (!in_array($format, ['table', 'json'])) {
                $io->error('Invalid format. Use "table" or "json".');

                return Command::FAILURE;
            }

            // Validate and fetch user
            $user = $this->validateAndGetUser($userId, $io);
            if (!$user instanceof UserInterface) {
                return Command::FAILURE;
            }

            // Get user permissions
            $userPermissions = $this->userAccessControlService->getUserPermissions($user);

            if ('json' === $format) {
                $this->outputJson($userPermissions, $user, $output);
            } else {
                $this->outputTable($userPermissions, $user, $io);
            }

            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $io->error('Validation Error: '.$e->getMessage());

            return Command::FAILURE;
        } catch (Exception $e) {
            $io->error('Unexpected error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function validateAndGetUser(string $userId, SymfonyStyle $io): ?UserInterface
    {
        if (in_array(trim($userId), ['', '0'], true)) {
            $io->error('User ID cannot be empty');

            return null;
        }

        $user = $this->userRepository->find($userId);
        if (null === $user) {
            $io->error(sprintf('User with ID "%s" not found', $userId));

            return null;
        }

        return $user;
    }

    private function outputTable(array $userPermissions, UserInterface $user, SymfonyStyle $io): void
    {
        $io->title(sprintf('User-Specific Permissions for "%s"', $user->getLogin()));

        if ([] === $userPermissions) {
            $io->note('No user-specific permissions found for this user.');

            return;
        }

        $io->definitionList(
            ['User ID' => $user->getId()],
            ['User Login'        => $user->getLogin()],
            ['Organization'      => $user->getOrga()?->getName() ?? 'N/A'],
            ['Customer'          => $user->getCurrentCustomer()?->getName() ?? 'N/A'],
            ['Total Permissions' => count($userPermissions)]
        );

        $table = new Table($io);
        $table->setHeaders(['Permission', 'Role', 'Granted Date', 'Modified Date']);

        foreach ($userPermissions as $permission) {
            $table->addRow([
                $permission->getPermission(),
                $permission->getRole()->getCode(),
                $permission->getCreationDate()->format('Y-m-d H:i:s'),
                $permission->getModificationDate()->format('Y-m-d H:i:s'),
            ]);
        }

        $table->render();
    }

    private function outputJson(array $userPermissions, UserInterface $user, OutputInterface $output): void
    {
        $data = [
            'user' => [
                'id'           => $user->getId(),
                'login'        => $user->getLogin(),
                'organization' => $user->getOrga()?->getName(),
                'customer'     => $user->getCurrentCustomer()?->getName(),
            ],
            'permissions' => [],
        ];

        foreach ($userPermissions as $permission) {
            $data['permissions'][] = [
                'permission'    => $permission->getPermission(),
                'role'          => $permission->getRole()->getCode(),
                'granted_date'  => $permission->getCreationDate()->format('c'),
                'modified_date' => $permission->getModificationDate()->format('c'),
            ];
        }

        $output->writeln(json_encode($data, JSON_PRETTY_PRINT));
    }
}
