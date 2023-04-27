<?php
declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Provider;


use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanUserBundle\Repository\UserRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class UserFromSecurityUserProvider
{
    private ?User $user = null;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserRepository $userRepository
    ) {
    }

    public function get(): ?User
    {
        if (null === $this->user) {
            $this->user = $this->fromToken($this->tokenStorage->getToken());
        }

        return $this->user;
    }

    public function fromToken(TokenInterface $token): ?User
    {
        if (!$token->getUser() instanceof SecurityUser) {
            return null;
        }

        return $this->userRepository->findOneBy(['login' => $token->getUser()->getUserIdentifier()]);
    }
    public function fromSecurityUser(SecurityUser $securityUser): ?User
    {
        return $this->userRepository->findOneBy(['login' => $securityUser->getUserIdentifier()]);
    }
}
