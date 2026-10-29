<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\UserReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\User\UserSecurityHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;
use Tests\Base\FunctionalTestCase;

/**
 * Setup report of the UserSecurityHandler with real services.
 */
class UserSecurityHandlerTwoFactorTest extends FunctionalTestCase
{
    private const FEATURE_ORGA_REQUIREMENT = 'feature_orga_two_factor_required';
    private const FEATURE_2FA = 'feature_2fa';
    private const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';
    private const EMAIL_CODE = '123456';

    protected ?UserSecurityHandler $sut = null;
    protected ?MockObject $messageBag = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $container = self::getContainer();
        $this->sut = new UserSecurityHandler(
            $container->get(TotpAuthenticator::class),
            $this->messageBag,
            $container->get(UserService::class),
            $container->get(UserReportEntryFactory::class),
            $container->get(ReportService::class),
            new NullLogger()
        );
    }

    protected function tearDown(): void
    {
        $this->sut = null;
        $this->messageBag = null;

        parent::tearDown();
    }

    public function testEnablingTotpWithValidCodeWritesOneSetupEntry(): void
    {
        $user = $this->arrangeUser(false, false);
        $user->setTotpSecret(self::TOTP_SECRET);

        $this->sut->handleUserSecurityPropertiesUpdate($user, ['twoFactorCode' => $this->validTotpCode()]);

        self::assertTrue($user->isTotpEnabled());
        $this->assertSingleSetupEntry($user, 'totp');
    }

    public function testEnablingEmailWithValidCodeWritesOneSetupEntry(): void
    {
        $user = $this->arrangeUser(false, false);
        $user->setEmailAuthCode(self::EMAIL_CODE);

        $this->sut->handleUserSecurityPropertiesUpdate($user, ['twoFactorCodeEmail' => self::EMAIL_CODE]);

        self::assertTrue($user->isEmailAuthEnabled());
        $this->assertSingleSetupEntry($user, 'email');
    }

    public function testInvalidCodesWriteNoEntry(): void
    {
        $user = $this->arrangeUser(false, false);
        $user->setTotpSecret(self::TOTP_SECRET);
        $user->setEmailAuthCode(self::EMAIL_CODE);

        $this->sut->handleUserSecurityPropertiesUpdate($user, ['twoFactorCode' => '000000', 'twoFactorCodeEmail' => '999999']);

        self::assertFalse($user->isTotpEnabled());
        self::assertFalse($user->isEmailAuthEnabled());
        self::assertSame([], $this->findSetupEntries($user));
    }

    public function testDisablingWritesNoEntry(): void
    {
        $user = $this->arrangeUser(true, false, false);
        $user->setTotpSecret(self::TOTP_SECRET);

        $this->sut->handleUserSecurityPropertiesUpdate($user, ['disableTwoFactorCode' => $this->validTotpCode()]);

        self::assertFalse($user->isTotpEnabled());
        self::assertSame([], $this->findSetupEntries($user));
    }

    /**
     * Logs in a user of one organisation.
     *
     * @param list<string> $disabledPermissions
     */
    private function arrangeUser(
        bool $totpEnabled,
        bool $emailEnabled,
        bool $orgaRequires = true,
        array $disabledPermissions = [],
    ): User {
        $user = UserFactory::createOne([
            'login'                => 'twofactor-user',
            'firstname'            => 'Tina',
            'lastname'             => 'Tester',
            'totpEnabled'          => $totpEnabled,
            'authCodeEmailEnabled' => $emailEnabled,
        ]);
        OrgaFactory::createOne([
            'deleted'           => false,
            'twoFactorRequired' => $orgaRequires,
            'users'             => [$user->_real()],
        ]);
        $user->_refresh();

        $this->logIn($user->_real());
        $this->enablePermissions([self::FEATURE_ORGA_REQUIREMENT, self::FEATURE_2FA]);
        if ([] !== $disabledPermissions) {
            $this->disablePermissions($disabledPermissions);
        }

        return $user->_real();
    }

    private function validTotpCode(): string
    {
        return TOTP::createFromSecret(self::TOTP_SECRET)->now();
    }

    /**
     * @return list<ReportEntry>
     */
    private function findSetupEntries(User $user): array
    {
        return self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(ReportEntry::class)
            ->findBy(['category' => ReportEntry::CATEGORY_SETUP_TWO_FACTOR, 'identifier' => $user->getId()]);
    }

    private function assertSingleSetupEntry(User $user, string $method): void
    {
        $entries = $this->findSetupEntries($user);

        self::assertCount(1, $entries);
        $message = json_decode($entries[0]->getMessage(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($method, $message['method']);
        self::assertSame($user->getId(), $message['userId']);
        self::assertSame($user->getEmail(), $message['userEmail']);
    }
}
