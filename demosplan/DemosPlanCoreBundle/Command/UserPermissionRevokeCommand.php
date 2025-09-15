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

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserPermissionRevokeCommand extends UserPermissionBaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('dplan:user:permission:revoke')
            ->setDescription('Revoke a specific permission from a user')
            ->setHelp('This command allows you to revoke a user-specific permission from a user.');

        $this->addCommonArguments();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userId = $input->getArgument(self::ARG_USER_ID);
        $permission = $input->getArgument(self::ARG_PERMISSION);
        $roleCode = $input->getOption(self::OPT_ROLE);

        $io->title('Revoke User-Specific Permission');

        try {
            // Validate and fetch user
            $user = $this->validateAndGetUser($userId, $io);
            if (null === $user) {
                return Command::FAILURE;
            }

            // Validate and fetch role
            $role = $this->validateAndGetRole($user, $roleCode, $io);
            if (null === $role) {
                return Command::FAILURE;
            }

            // Validate permission name
            if (!$this->validatePermissionName($permission, $io)) {
                return Command::FAILURE;
            }

            return $this->revokePermission($user, $role, $permission, $io);
        } catch (InvalidArgumentException $e) {
            $io->error(self::ERROR_VALIDATION.$e->getMessage());
        } catch (Exception $e) {
            $io->error(self::ERROR_UNEXPECTED.$e->getMessage());
        }

        return Command::FAILURE;
    }

    private function revokePermission($user, $role, string $permission, SymfonyStyle $io): int
    {
        // Check if permission exists
        if (!$this->userAccessControlService->userPermissionExists($user, $permission, $role)) {
            $io->warning(sprintf(
                'User "%s" does not have permission "%s" for role "%s"',
                $user->getLogin(),
                $permission,
                $role->getCode()
            ));

            return Command::SUCCESS;
        }

        // Revoke the permission
        $removed = $this->userAccessControlService->removeUserPermission($user, $permission, $role);

        if (!$removed) {
            $io->error('Failed to revoke permission. The permission may not exist.');

            return Command::FAILURE;
        }

        $io->success('Permission revoked successfully!');

        $this->displayUserInfo($user, $role, $permission, $io);
        $io->definitionList(['Revoked at' => date('Y-m-d H:i:s')]);

        return Command::SUCCESS;
    }
}
