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
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Base class for user permission commands to reduce code duplication.
 */
abstract class UserPermissionBaseCommand extends CoreCommand
{
    public function __construct(
        protected UserAccessControlService $userAccessControlService,
        protected UserRepository $userRepository,
        protected RoleHandler $roleHandler,
        ParameterBagInterface $parameterBag,
    ) {
        parent::__construct($parameterBag);
    }

    protected function addCommonArguments(): void
    {
        $this
            ->addArgument('user-id', InputArgument::REQUIRED, 'User ID (UUID)')
            ->addArgument('permission', InputArgument::REQUIRED, 'Permission name (e.g., area_admin_procedures)')
            ->addOption(
                'role',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Specific role code (e.g., RMOPSA). If not provided, uses user\'s first role.'
            );
    }

    protected function validateAndGetUser(string $userId, SymfonyStyle $io): ?UserInterface
    {
        if (empty(trim($userId))) {
            $io->error('User ID cannot be empty');

            return null;
        }

        $user = $this->userRepository->find($userId);
        if (null === $user) {
            $io->error(sprintf('User with ID "%s" not found', $userId));

            return null;
        }

        // Validate user has proper organization setup
        if (null === $user->getOrga()) {
            $io->error(sprintf('User "%s" does not have an organization assigned', $user->getLogin()));

            return null;
        }

        if (null === $user->getCurrentCustomer()) {
            $io->error(sprintf('User "%s" does not have a current customer assigned', $user->getLogin()));

            return null;
        }

        if ($user->getDplanRoles()->isEmpty()) {
            $io->error(sprintf('User "%s" does not have any roles assigned', $user->getLogin()));

            return null;
        }

        return $user;
    }

    protected function validateAndGetRole(UserInterface $user, ?string $roleCode, SymfonyStyle $io): ?RoleInterface
    {
        if (null === $roleCode) {
            // Use user's first role
            $role = $user->getDplanRoles()->first();
            if (false === $role) {
                $io->error('User has no roles assigned');

                return null;
            }

            return $role;
        }

        // Find the specific role
        $roles = $this->roleHandler->getUserRolesByCodes([$roleCode]);
        if (empty($roles)) {
            $io->error(sprintf('Role with code "%s" not found', $roleCode));

            return null;
        }

        $role = $roles[0];

        // Verify user has this role (compare by code, not object identity)
        $userRoleCodes = $user->getDplanRoles()->map(fn (Role $r) => $r->getCode())->toArray();
        if (!in_array($roleCode, $userRoleCodes, true)) {
            $io->error(sprintf(
                'User "%s" does not have role "%s". Available roles: %s',
                $user->getLogin(),
                $roleCode,
                implode(', ', $userRoleCodes)
            ));

            return null;
        }

        return $role;
    }

    protected function validatePermissionName(string $permission, SymfonyStyle $io): bool
    {
        if (empty(trim($permission))) {
            $io->error('Permission name cannot be empty');

            return false;
        }

        // Basic validation for permission name format
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $permission)) {
            $io->error('Permission name must start with a letter and contain only letters, numbers, and underscores');

            return false;
        }

        return true;
    }

    protected function displayUserInfo(UserInterface $user, RoleInterface $role, string $permission, SymfonyStyle $io): void
    {
        $io->definitionList(
            ['User ID' => $user->getId()],
            ['User Login'   => $user->getLogin()],
            ['Organization' => $user->getOrga()?->getName() ?? 'N/A'],
            ['Customer'     => $user->getCurrentCustomer()?->getName() ?? 'N/A'],
            ['Role'         => $role->getCode()],
            ['Permission'   => $permission]
        );
    }

    protected function getUserContextInfo(UserInterface $user): array
    {
        return [
            'id'           => $user->getId(),
            'login'        => $user->getLogin(),
            'organization' => $user->getOrga()?->getName(),
            'customer'     => $user->getCurrentCustomer()?->getName(),
        ];
    }
}