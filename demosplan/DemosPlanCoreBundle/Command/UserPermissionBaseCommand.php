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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
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
    // Argument/Option names
    protected const ARG_USER_ID = 'user-id';
    protected const ARG_PERMISSION = 'permission';
    protected const OPT_ROLE = 'role';
    protected const OPT_ROLE_SHORT = 'r';

    // Error messages
    protected const ERROR_USER_ID_EMPTY = 'User ID cannot be empty';
    protected const ERROR_USER_NOT_FOUND = 'User with ID "%s" not found';
    protected const ERROR_USER_NO_ORGANIZATION = 'User "%s" does not have an organization assigned';
    protected const ERROR_USER_NO_CUSTOMER = 'User "%s" does not have a current customer assigned';
    protected const ERROR_USER_NO_ROLES = 'User "%s" does not have any roles assigned';
    protected const ERROR_USER_NO_ROLES_GENERIC = 'User has no roles assigned';
    protected const ERROR_ROLE_NOT_FOUND = 'Role with code "%s" not found';
    protected const ERROR_USER_ROLE_MISMATCH = 'User "%s" does not have role "%s". Available roles: %s';
    protected const ERROR_PERMISSION_EMPTY = 'Permission name cannot be empty';
    protected const ERROR_PERMISSION_FORMAT = 'Permission name must start with a letter and contain only letters, numbers, and underscores';

    // Labels
    protected const LABEL_USER_ID = 'User ID';
    protected const LABEL_USER_LOGIN = 'User Login';
    protected const LABEL_ORGANIZATION = 'Organization';
    protected const LABEL_CUSTOMER = 'Customer';
    protected const LABEL_ROLE = 'Role';
    protected const LABEL_PERMISSION = 'Permission';
    protected const LABEL_NOT_AVAILABLE = 'N/A';

    // Exception messages
    protected const ERROR_VALIDATION = 'Validation Error: ';
    protected const ERROR_UNEXPECTED = 'Unexpected error: ';

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
            ->addArgument(self::ARG_USER_ID, InputArgument::REQUIRED, 'User ID (UUID)')
            ->addArgument(self::ARG_PERMISSION, InputArgument::REQUIRED, 'Permission name (e.g., area_admin_procedures)')
            ->addOption(
                self::OPT_ROLE,
                self::OPT_ROLE_SHORT,
                InputOption::VALUE_OPTIONAL,
                'Specific role code (e.g., RMOPSA). If not provided, uses user\'s first role.'
            );
    }

    protected function validateAndGetUser(string $userId, SymfonyStyle $io): ?UserInterface
    {
        if (in_array(trim($userId), ['', '0'], true)) {
            $io->error(self::ERROR_USER_ID_EMPTY);

            return null;
        }

        $user = $this->userRepository->find($userId);
        if (null === $user) {
            $io->error(sprintf(self::ERROR_USER_NOT_FOUND, $userId));

            return null;
        }

        $validationResult = $this->validateUserConfiguration($user, $io);
        if (!$validationResult) {
            return null;
        }

        return $user;
    }

    private function validateUserConfiguration(UserInterface $user, SymfonyStyle $io): bool
    {
        // Validate user has proper organization setup
        if (!$user->getOrga() instanceof OrgaInterface) {
            $io->error(sprintf(self::ERROR_USER_NO_ORGANIZATION, $user->getLogin()));

            return false;
        }

        if (!$user->getCurrentCustomer() instanceof CustomerInterface) {
            $io->error(sprintf(self::ERROR_USER_NO_CUSTOMER, $user->getLogin()));

            return false;
        }

        if ($user->getDplanRoles()->isEmpty()) {
            $io->error(sprintf(self::ERROR_USER_NO_ROLES, $user->getLogin()));

            return false;
        }

        return true;
    }

    protected function validateAndGetRole(UserInterface $user, ?string $roleCode, SymfonyStyle $io): ?RoleInterface
    {
        if (null === $roleCode) {
            return $this->getDefaultUserRole($user, $io);
        }

        return $this->getSpecificUserRole($user, $roleCode, $io);
    }

    private function getDefaultUserRole(UserInterface $user, SymfonyStyle $io): ?RoleInterface
    {
        // Use user's first role
        $role = $user->getDplanRoles()->first();
        if (false === $role) {
            $io->error(self::ERROR_USER_NO_ROLES_GENERIC);

            return null;
        }

        return $role;
    }

    private function getSpecificUserRole(UserInterface $user, string $roleCode, SymfonyStyle $io): ?RoleInterface
    {
        // Find the specific role
        $roles = $this->roleHandler->getUserRolesByCodes([$roleCode]);
        if ([] === $roles) {
            $io->error(sprintf(self::ERROR_ROLE_NOT_FOUND, $roleCode));

            return null;
        }

        $role = $roles[0];

        // Verify user has this role (compare by code, not object identity)
        $userRoleCodes = $user->getDplanRoles()->map(fn (Role $r) => $r->getCode())->toArray();
        if (!in_array($roleCode, $userRoleCodes, true)) {
            $io->error(sprintf(
                self::ERROR_USER_ROLE_MISMATCH,
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
        if (in_array(trim($permission), ['', '0'], true)) {
            $io->error(self::ERROR_PERMISSION_EMPTY);

            return false;
        }

        // Basic validation for permission name format
        if (!preg_match('/^[a-zA-Z]\w*$/', $permission)) {
            $io->error(self::ERROR_PERMISSION_FORMAT);

            return false;
        }

        return true;
    }

    protected function displayUserInfo(UserInterface $user, RoleInterface $role, string $permission, SymfonyStyle $io): void
    {
        $io->definitionList(
            [self::LABEL_USER_ID => $user->getId()],
            [self::LABEL_USER_LOGIN   => $user->getLogin()],
            [self::LABEL_ORGANIZATION => $user->getOrga()?->getName() ?? self::LABEL_NOT_AVAILABLE],
            [self::LABEL_CUSTOMER     => $user->getCurrentCustomer()?->getName() ?? self::LABEL_NOT_AVAILABLE],
            [self::LABEL_ROLE         => $role->getCode()],
            [self::LABEL_PERMISSION   => $permission]
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
