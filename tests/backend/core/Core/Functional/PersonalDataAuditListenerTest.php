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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\PersonalDataAuditLog;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventListener\PersonalDataAuditListener;
use Tests\Base\FunctionalTestCase;

class PersonalDataAuditListenerTest extends FunctionalTestCase
{
    private ?PersonalDataAuditListener $listener = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = self::getContainer()->get(PersonalDataAuditListener::class);
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    public function testUpdateUserEmailCreatesAuditEntry(): void
    {
        // Arrange
        $user = UserFactory::createOne([
            'email' => 'original@example.com',
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
        ]);
        $userId = $user->getId();
        $this->getEntityManager()->clear();

        $user = $this->getEntityManager()->find(User::class, $userId);

        // Act
        $user->setEmail('changed@example.com');
        $this->getEntityManager()->flush();

        // Assert
        $entries = $this->getEntries(PersonalDataAuditLog::class, [
            'entityId' => $userId,
            'entityField' => 'email',
            'changeType' => PersonalDataAuditLog::CHANGE_TYPE_UPDATE,
        ]);

        static::assertCount(1, $entries);
        static::assertSame('original@example.com', $entries[0]->getPreUpdateValue());
        static::assertSame('changed@example.com', $entries[0]->getPostUpdateValue());
        static::assertFalse($entries[0]->isSensitiveField());
    }

    public function testPasswordChangeIsMasked(): void
    {
        // Arrange
        $user = UserFactory::createOne(['password' => 'old_hash']);
        $userId = $user->getId();
        $this->getEntityManager()->clear();

        $user = $this->getEntityManager()->find(User::class, $userId);

        // Act
        $user->setPassword('new_hash');
        $this->getEntityManager()->flush();

        // Assert
        $entries = $this->getEntries(PersonalDataAuditLog::class, [
            'entityId' => $userId,
            'entityField' => 'password',
            'changeType' => PersonalDataAuditLog::CHANGE_TYPE_UPDATE,
        ]);

        static::assertCount(1, $entries);
        static::assertSame(PersonalDataAuditLog::SENSITIVE_MASK, $entries[0]->getPreUpdateValue());
        static::assertSame(PersonalDataAuditLog::SENSITIVE_MASK, $entries[0]->getPostUpdateValue());
        static::assertTrue($entries[0]->isSensitiveField());
    }

    public function testNullToEmptyStringIsSkipped(): void
    {
        // Arrange
        $user = UserFactory::createOne(['firstname' => null]);
        $userId = $user->getId();
        $this->getEntityManager()->clear();

        $user = $this->getEntityManager()->find(User::class, $userId);

        // Act
        $user->setFirstname('');
        $this->getEntityManager()->flush();

        // Assert
        $entries = $this->getEntries(PersonalDataAuditLog::class, [
            'entityId' => $userId,
            'entityField' => 'firstname',
            'changeType' => PersonalDataAuditLog::CHANGE_TYPE_UPDATE,
        ]);

        static::assertCount(0, $entries);
    }

    public function testUntrackedFieldIsIgnored(): void
    {
        // Arrange: 'providedByIdentityProvider' is not in the audit mapping
        $user = UserFactory::createOne(['providedByIdentityProvider' => false]);
        $userId = $user->getId();
        $this->getEntityManager()->clear();

        $user = $this->getEntityManager()->find(User::class, $userId);

        // Act
        $user->setProvidedByIdentityProvider(true);
        $this->getEntityManager()->flush();

        // Assert
        $entries = $this->getEntries(PersonalDataAuditLog::class, [
            'entityId' => $userId,
            'entityField' => 'providedByIdentityProvider',
        ]);

        static::assertCount(0, $entries);
    }

    public function testDisabledListenerCreatesNoEntries(): void
    {
        // Arrange
        $user = UserFactory::createOne(['email' => 'before@example.com']);
        $userId = $user->getId();
        $this->getEntityManager()->clear();

        $user = $this->getEntityManager()->find(User::class, $userId);
        $this->listener->disable();

        // Act
        $user->setEmail('after@example.com');
        $this->getEntityManager()->flush();

        // Assert
        $entries = $this->getEntries(PersonalDataAuditLog::class, [
            'entityId' => $userId,
            'entityField' => 'email',
            'changeType' => PersonalDataAuditLog::CHANGE_TYPE_UPDATE,
        ]);

        static::assertCount(0, $entries);

        // Cleanup
        $this->listener->enable();
    }
}
