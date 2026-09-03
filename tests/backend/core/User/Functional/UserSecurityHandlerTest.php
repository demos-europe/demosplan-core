<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\UserReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\User\UserSecurityHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;
use Tests\Base\FunctionalTestCase;

class UserSecurityHandlerTest extends FunctionalTestCase
{
    private $totpAuthenticator;
    private $messageBag;
    private $userService;
    private $userReportEntryFactory;
    private $reportService;
    private $userSecurityHandler;
    private $user;

    protected function setUp(): void
    {
        $this->totpAuthenticator = $this->createMock(TotpAuthenticator::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->userService = $this->createMock(UserService::class);
        $this->user = $this->createMock(User::class);
        $this->userReportEntryFactory = $this->createMock(UserReportEntryFactory::class);
        $this->reportService = $this->createMock(ReportService::class);
        $this->userSecurityHandler = new UserSecurityHandler(
            $this->totpAuthenticator,
            $this->messageBag,
            $this->userService,
            $this->userReportEntryFactory,
            $this->reportService
        );
    }

    public function testEnablesTotpWhenValidCodeProvided()
    {
        $updateUserData = ['twoFactorCode' => 'validCode'];
        $this->totpAuthenticator->method('checkCode')->willReturn(true);
        $this->user->expects($this->once())->method('setTotpEnabled')->with(true);

        $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($this->user, $updateUserData);
    }

    public function testAddsErrorMessageWhenInvalidTotpCode()
    {
        $updateUserData = ['twoFactorCode' => 'invalidCode'];
        $this->totpAuthenticator->method('checkCode')->willReturn(false);
        $this->messageBag->expects($this->once())->method('add')->with('error', 'error.2fa.code.invalid');

        $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($this->user, $updateUserData);
    }

    public function testDisablesTotpWhenDisableCodeProvided()
    {
        $updateUserData = ['disableTwoFactorCode' => 'validDisableCode'];
        $this->totpAuthenticator->method('checkCode')->willReturn(true);
        $this->user->expects($this->once())->method('setTotpEnabled')->with(false);

        $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($this->user, $updateUserData);
    }

    public function testEnablesEmailAuthWhenValidEmailCodeProvided()
    {
        $updateUserData = ['twoFactorCodeEmail' => 'validEmailCode'];
        $this->user->method('getEmailAuthCode')->willReturn('validEmailCode');
        $this->user->expects($this->once())->method('setAuthCodeEmailEnabled')->with(true);

        $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($this->user, $updateUserData);
    }

    public function testAddsErrorMessageWhenInvalidEmailAuthCode()
    {
        $updateUserData = ['twoFactorCodeEmail' => 'invalidEmailCode'];
        $this->user->method('getEmailAuthCode')->willReturn('differentEmailCode');
        $this->messageBag->expects($this->once())->method('add')->with('error', 'error.2fa.code.invalid');

        $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($this->user, $updateUserData);
    }

    public function testResetTwoFactorAuthenticationClearsBothSecondFactors(): void
    {
        $user = new User();
        $user->setTotpEnabled(true);
        $user->setTotpSecret('someSecret');
        $user->setAuthCodeEmailEnabled(true);
        $user->setEmailAuthCode('123456');
        $this->userService->expects($this->once())->method('updateUserObject')->with($user);
        $reportEntry = new ReportEntry();
        $this->userReportEntryFactory->expects($this->once())
            ->method('createTwoFactorResetEntry')->with($user)->willReturn($reportEntry);
        $this->reportService->expects($this->once())
            ->method('persistAndFlushReportEntry')->with($reportEntry);

        $this->userSecurityHandler->resetTwoFactorAuthentication($user);

        self::assertFalse($user->isTotpEnabled());
        self::assertNull($user->getTotpSecret());
        self::assertFalse($user->isEmailAuthEnabled());
        self::assertSame('', $user->getEmailAuthCode());
    }

    public function testDisablesEmailAuthWhenDisableEmailCodeProvided()
    {
        $updateUserData = ['disableTwoFactorCodeEmail' => 'validDisableEmailCode'];
        $this->user->method('getEmailAuthCode')->willReturn('validDisableEmailCode');
        $this->user->expects($this->once())->method('setAuthCodeEmailEnabled')->with(false);

        $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($this->user, $updateUserData);
    }
}
