<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityUser implements UserInterface, EquatableInterface, PasswordAuthenticatedUserInterface
{
    private string $id;
    private ?string $email;
    private ?string $password;
    private array  $roles;
    private ?string $login;

    public function __construct(User $user)
    {
        $this->id = $user->getId();
        $this->email = $user->getEmail();
        $this->password = $user->getPassword();
        $this->login = $user->getLogin();
        $this->roles = $user->getDplanRolesArray();
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
        return null;
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
        if (!$user instanceof self) {
            return false;
        }

        if ($this->getRoles() !== $user->getRoles()) {
            return false;
        }

        if ($this->login !== $user->getUserIdentifier()) {
            return false;
        }

        return true;
    }
}
