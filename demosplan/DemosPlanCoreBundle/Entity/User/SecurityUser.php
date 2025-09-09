<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements UserInterface, EquatableInterface, PasswordAuthenticatedUserInterface
{
    private readonly string $id;
    private readonly ?string $email;
    private readonly ?string $password;
    private readonly array $roles;
    private readonly ?string $login;
    private readonly ?string $salt;

    public function __construct(User $user)
    {
        $this->id = $user->getId();
        $this->email = $user->getEmail();
        $this->password = $user->getPassword();
        $this->login = $user->getLogin();
        $this->roles = $user->getDplanRolesArray();
        $this->salt = $user->getSalt();
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getSalt(): ?string
    {
        return $this->salt;
    }

    public function getUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getUserIdentifier(): string
    {
        return $this->login;
    }

    public function eraseCredentials(): void
    {
        // do nothing to be able to update password has later on
    }

    public function isEqualTo(UserInterface $user): bool
    {
        $userRoles = $user->getRoles();
        $thisRoles = $this->getRoles();
        sort($userRoles);
        sort($thisRoles);
        if ($thisRoles !== $userRoles) {
            return false;
        }

        if ($this->login !== $user->getUserIdentifier()) {
            return false;
        }

        return true;
    }

    public function isLoggedIn(): bool
    {
        return !in_array(RoleInterface::GUEST, $this->roles, true);
    }
}
