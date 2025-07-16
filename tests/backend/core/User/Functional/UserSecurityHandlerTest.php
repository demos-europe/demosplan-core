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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserSecurityHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;
use Tests\Base\FunctionalTestCase;

class UserSecurityHandlerTest extends FunctionalTestCase
{
    private $totpAuthenticator;
    private $messageBag;
    private $userService;
    private $userSecurityHandler;
    private $user;

    protected function setUp(): void
    {
        $this->totpAuthenticator = $this->createMock(TotpAuthenticator::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->userService = $this->createMock(UserService::class);
        $this->user = $this->createMock(User::class);
        $this->userSecurityHandler = new UserSecurityHandler(
            $this->totpAuthenticator,
            $this->messageBag,
            $this->userService
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

    public function testDisablesEmailAuthWhenDisableEmailCodeProvided()
    {
        $updateUserData = ['disableTwoFactorCodeEmail' => 'validDisableEmailCode'];
        $this->user->method('getEmailAuthCode')->willReturn('validDisableEmailCode');
        $this->user->expects($this->once())->method('setAuthCodeEmailEnabled')->with(false);

        $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($this->user, $updateUserData);
    }
}
