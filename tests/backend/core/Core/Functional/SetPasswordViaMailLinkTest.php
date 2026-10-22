<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserPasswordHistory;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHasher;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Tests\Base\AbstractApiTest;

/**
 * Setting a password via the link of the "recover password" / invitation mail.
 */
class SetPasswordViaMailLinkTest extends AbstractApiTest
{
    private const CURRENT_PASSWORD = 'Xk9!mQ2#vLp8wZ';
    private const NEW_PASSWORD = 'Rt7$nB4&hJc6yD';

    protected ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['area_demosplan']);
    }

    public function testAlreadyUsedPasswordIsRejectedAndUserCanRetry(): void
    {
        // Arrange
        $this->setCurrentPassword(self::CURRENT_PASSWORD);
        $historyCountBefore = $this->countPasswordHistory();
        $hashBefore = $this->user->getPassword();

        // Act
        $this->postNewPassword(self::CURRENT_PASSWORD);

        // Assert
        self::assertResponseRedirects($this->getFormUrl());
        self::assertSame($historyCountBefore, $this->countPasswordHistory());
        self::assertSame($hashBefore, $this->getFreshUser()->getPassword());
    }

    public function testNewPasswordIsSaved(): void
    {
        // Arrange
        $this->setCurrentPassword(self::CURRENT_PASSWORD);
        $historyCountBefore = $this->countPasswordHistory();

        // Act
        $this->postNewPassword(self::NEW_PASSWORD);

        // Assert
        self::assertSame($historyCountBefore + 1, $this->countPasswordHistory());
        self::assertTrue($this->isPasswordValid(self::NEW_PASSWORD));
    }

    public function testSurroundingWhitespaceIsTrimmedBeforeSaving(): void
    {
        // Arrange
        $this->setCurrentPassword(self::CURRENT_PASSWORD);

        // Act
        $this->postNewPassword(' '.self::NEW_PASSWORD." \n");

        // Assert: the login form trims, so the stored password has to be the trimmed one
        self::assertTrue($this->isPasswordValid(self::NEW_PASSWORD));
    }

    public function testInvalidTokenDoesNotChangeThePassword(): void
    {
        // Arrange
        $this->setCurrentPassword(self::CURRENT_PASSWORD);
        $hashBefore = $this->user->getPassword();

        // Act
        $this->client->request(
            'POST',
            "/user/{$this->user->getId()}/setpass/invalidtoken",
            ['password' => self::NEW_PASSWORD, 'password_new_2' => self::NEW_PASSWORD]
        );

        // Assert
        self::assertSame($hashBefore, $this->getFreshUser()->getPassword());
        self::assertSame(0, $this->countPasswordHistoryEntriesFor(self::NEW_PASSWORD));
    }

    private function setCurrentPassword(string $password): void
    {
        $this->getContainer()->get(UserService::class)
            ->changePassword($this->user->getId(), '', $password, false);
        $this->getEntityManager()->refresh($this->user);
    }

    private function postNewPassword(string $password): void
    {
        $this->client->request(
            'POST',
            "/user/{$this->user->getId()}/setpass/{$this->getToken()}",
            ['password' => $password, 'password_new_2' => $password]
        );
    }

    private function getToken(): string
    {
        return $this->getContainer()->get(UserHasher::class)->getPasswordEditHash($this->user);
    }

    private function getFormUrl(): string
    {
        return "/doubleoptin/{$this->user->getId()}/{$this->getToken()}";
    }

    private function countPasswordHistory(): int
    {
        return count($this->getEntityManager()
            ->getRepository(UserPasswordHistory::class)
            ->findBy(['user' => $this->user->getId()]));
    }

    private function countPasswordHistoryEntriesFor(string $password): int
    {
        $hasher = $this->getContainer()->get('security.password_hasher_factory')->getPasswordHasher($this->user);
        $matching = array_filter(
            $this->getEntityManager()->getRepository(UserPasswordHistory::class)->findBy(['user' => $this->user->getId()]),
            static fn (UserPasswordHistory $entry): bool => $hasher->verify($entry->getHashedPassword(), $password)
        );

        return count($matching);
    }

    private function isPasswordValid(string $password): bool
    {
        return $this->getContainer()->get(UserPasswordHasherInterface::class)
            ->isPasswordValid($this->getFreshUser(), $password);
    }

    private function getFreshUser(): User
    {
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->find(User::class, $this->user->getId());
    }

    protected function getServerParameters(): array
    {
        return [];
    }
}
