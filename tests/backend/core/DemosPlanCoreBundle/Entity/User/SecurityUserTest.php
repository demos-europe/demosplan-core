<?php
declare(strict_types=1);

namespace Tests\Core\DemosPlanCoreBundle\Entity\User;

use demosplan\DemosPlanCoreBundle\Entity\User\SecurityUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use PHPUnit\Framework\TestCase;

class SecurityUserTest extends TestCase
{
    public function testGetRoles(): void
    {
        $securityUser = $this->getSecurityUser();

        $expectedRoles = ['ROLE_ADMIN', 'ROLE_USER'];
        $this->assertEquals($expectedRoles, $securityUser->getRoles());
    }

    public function testGetPassword(): void
    {
        $securityUser = $this->getSecurityUser();

        $this->assertEquals('password123', $securityUser->getPassword());
    }

    public function testGetSalt()
    {
        $securityUser = $this->getSecurityUser();

        $this->assertEquals('randomsalt', $securityUser->getSalt());
    }

    public function testGetUsername(): void
    {
        $securityUser = $this->getSecurityUser();

        $this->assertEquals('test_user', $securityUser->getUsername());
    }

    public function testGetUserIdentifier(): void
    {
        $securityUser = $this->getSecurityUser();

        $this->assertEquals('test_user', $securityUser->getUserIdentifier());
    }

    public function testIsEqualTo(): void
    {
        $securityUser1 = $this->getSecurityUser(['ROLE_ADMIN', 'ROLE_USER']);
        $securityUser2 = $this->getSecurityUser(['ROLE_USER', 'ROLE_ADMIN']);

        $this->assertTrue($securityUser1->isEqualTo($securityUser2));

        $securityUser1 = $this->getSecurityUser(['ROLE_USER', 'ROLE_ADMIN']);
        $securityUser2 = $this->getSecurityUser(['ROLE_USER', 'ROLE_ADMIN']);

        $this->assertTrue($securityUser1->isEqualTo($securityUser2));
    }

    public function testIsLoggedIn(): void
    {
        $securityUser = $this->getSecurityUser();

        $this->assertTrue($securityUser->isLoggedIn());

        $securityUser = $this->getSecurityUser(['RGUEST']);

        $this->assertFalse($securityUser->isLoggedIn());
    }

    private function getSecurityUser(array $roles = ['ROLE_ADMIN', 'ROLE_USER']): SecurityUser
    {
        $userMock = $this->createMock(User::class);
        $userId = '123';
        $email = 'test@example.com';
        $password = 'password123';
        $login = 'test_user';
        $salt = 'randomsalt';

        // Configure the mock to return specific values
        $userMock->method('getId')->willReturn($userId);
        $userMock->method('getEmail')->willReturn($email);
        $userMock->method('getPassword')->willReturn($password);
        $userMock->method('getLogin')->willReturn($login);
        $userMock->method('getDplanRolesArray')->willReturn($roles);
        $userMock->method('getSalt')->willReturn($salt);

        return new SecurityUser($userMock);
    }
}
