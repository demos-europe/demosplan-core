<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * dplan:data:modify-user.
 */
class ModifyUserCommand extends CoreCommand
{
    // lazy load command
    protected static $defaultName = 'dplan:data:modify-user';
    protected static $defaultDescription = 'Reset passwords of users for each allowed role to allow login.';

    /** @var UserService */
    protected $userService;

    /** @var string */
    protected $standardPassword;

    public function __construct(ParameterBagInterface $parameterBag, UserService $userService, string $name = null)
    {
        $this->userService = $userService;
        parent::__construct($parameterBag, $name);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->standardPassword = $this->parameterBag->get('alternative_login_testuser_defaultpass') ?? '';
        if ('' === trim($this->standardPassword)) {
            $output->writeln('Standard-password needs to be defined configuration');

            return Command::FAILURE;
        }

        /** @var array<int, string> $organisationsIds */
        $organisationsIds = $this->parameterBag->get('organisationIds_user_to_modify') ?? [];

        try {
            $users = $this->resetPasswordOfUsersOfOrganisations($organisationsIds);
        } catch (Exception $e) {
            $output->writeln('Reset password for users of specific organisations failed.');

            return Command::FAILURE;
        }
        try {
            $this->resetPasswordOfUsersPerRole(3, $users);
        } catch (Exception $e) {
            $output->writeln('Reset password for users of each role failed.');

            return Command::FAILURE;
        }

        $output->writeln('Updated users to login with');

        return Command::SUCCESS;
    }

    /**
     * @param array<int, string> $organisationsIds
     *
     * @return array<int, User>
     *
     * @throws Exception
     */
    private function resetPasswordOfUsersOfOrganisations(array $organisationsIds): array
    {
        $changedUsers = [];
        foreach ($organisationsIds as $organisationsId) {
            $users = $this->userService->getUsersOfOrganisation($organisationsId);
            foreach ($users as $user) {
                $user->setPassword(md5($this->standardPassword));
                $changedUsers[] = $user;
            }
        }
        $this->userService->updateUserObjects($changedUsers);

        return $changedUsers;
    }

    /**
     * @param int              $amountOfUsersPerRole limits the user to reset the password for per role
     * @param array<int, User> $usersToExclude
     *
     * @throws Exception
     */
    private function resetPasswordOfUsersPerRole(int $amountOfUsersPerRole, array $usersToExclude = []): void
    {
        $changedUsers = collect([]);

        /** @var array<int, string> $allowedRoles */
        $allowedRoles = $this->parameterBag->get('roles_allowed');

        // avoid system-role:
        $allowedRoles = collect($allowedRoles)->mapWithKeys(static function (string $roleName) {
            return [$roleName => $roleName];
        })->forget(Role::GUEST)->all();

        $usersIdsToExclude = collect($usersToExclude)->mapWithKeys(static function (User $item) {
            return [$item->getId() => $item->getId()];
        });

        // avoid system-user:
        $usersIdsToExclude->put(User::ANONYMOUS_USER_ID, User::ANONYMOUS_USER_ID);

        foreach ($allowedRoles as $role) {
            $usersOfSpecificRole = collect($this->userService->getUsersOfRole($role));

            // undeleted only + mapping
            $mappedUsers = $usersOfSpecificRole->filter(static function (User $user) {
                return !$user->isDeleted();
            })->mapWithKeys(static function (User $user) {
                return [$user->getId() => $user];
            });

            // filter users to exclude
            $filteredUsers = $mappedUsers->except($usersIdsToExclude);

            // avoid exception in case of less users available than given $amountOfUsersPerRole
            if ($amountOfUsersPerRole < $filteredUsers->count()) {
                $filteredUsers = $filteredUsers->random($amountOfUsersPerRole)->mapWithKeys(static function (User $user) {
                    return [$user->getId() => $user];
                });
            }

            // set new password for filtered users
            $updatedUsers = $filteredUsers->each(function (User $user) {
                $user->setPassword(md5($this->standardPassword));
            });

            // add filtered + updated users to list of changed users
            $changedUsers = $changedUsers->merge($updatedUsers);

            // exclude filtered + updated users in further execution to get a more distinct result
            $updatedUserIds = $updatedUsers->mapWithKeys(static function (User $item) {
                return [$item->getId() => $item->getId()];
            });

            // add filtered + updated users to list of users to exclude
            $usersIdsToExclude = $usersIdsToExclude->merge($updatedUserIds);
        }

        $this->userService->updateUserObjects($changedUsers->all());
    }
}
