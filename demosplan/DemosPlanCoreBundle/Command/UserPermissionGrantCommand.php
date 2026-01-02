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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'dplan:user:permission:grant', description: 'Grant a specific permission to a user')]
class UserPermissionGrantCommand extends UserPermissionBaseCommand
{
    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to grant a specific permission to a user beyond their role-based permissions.');

        $this->addCommonArguments();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userId = $input->getArgument(self::ARG_USER_ID);
        $permission = $input->getArgument(self::ARG_PERMISSION);
        $roleCode = $input->getOption(self::OPT_ROLE);

        $io->title('Grant User-Specific Permission');

        try {
            // Validate and fetch user
            $user = $this->validateAndGetUser($userId, $io);
            if (!$user instanceof UserInterface) {
                return Command::FAILURE;
            }

            // Validate and fetch role
            $role = $this->validateAndGetRole($user, $roleCode, $io);
            if (!$role instanceof RoleInterface) {
                return Command::FAILURE;
            }

            // Validate permission name
            if (!$this->validatePermissionName($permission, $io)) {
                return Command::FAILURE;
            }

            return $this->grantPermission($user, $role, $permission, $io);
        } catch (InvalidArgumentException $e) {
            $io->error(self::ERROR_VALIDATION.$e->getMessage());
        } catch (Exception $e) {
            $io->error(self::ERROR_UNEXPECTED.$e->getMessage());
        }

        return Command::FAILURE;
    }

    private function grantPermission($user, $role, string $permission, SymfonyStyle $io): int
    {
        // Check if permission already exists
        if ($this->userAccessControlService->userPermissionExists($user, $permission, $role)) {
            $io->warning(sprintf(
                'User "%s" already has permission "%s" for role "%s"',
                $user->getLogin(),
                $permission,
                $role->getCode()
            ));

            return Command::SUCCESS;
        }

        // Grant the permission
        $userPermission = $this->userAccessControlService->createUserPermission($user, $permission, $role);

        $io->success('Permission granted successfully!');

        $this->displayUserInfo($user, $role, $permission, $io);
        $io->definitionList(['Granted at' => $userPermission->getCreationDate()->format('Y-m-d H:i:s')]);

        return Command::SUCCESS;
    }
}
