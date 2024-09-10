<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticator;

class UserSecurityHandler
{
    public function __construct(
        private readonly TotpAuthenticator $totpAuthenticator,
        private readonly MessageBagInterface $messageBag,
        private readonly UserService $userService,
    ) {
    }

    public function handleUserSecurityPropertiesUpdate(UserInterface $user, array $updateUserData): UserInterface
    {
        $user = $this->handeTotp($updateUserData, $user);

        return $this->handeEmailAuth($updateUserData, $user);
    }

    private function handeTotp(array $updateUserData, UserInterface $user): UserInterface
    {
        if (isset($updateUserData['twoFactorCode']) && '' !== $updateUserData['twoFactorCode']) {
            if ($this->totpAuthenticator->checkCode($user, $updateUserData['twoFactorCode'])) {
                $user->setTotpEnabled(true);
                $this->userService->updateUserObject($user);
            } else {
                $this->messageBag->add('error', 'error.2fa.code.invalid');
            }
        }

        if (isset($updateUserData['disableTwoFactorCode']) && '' !== $updateUserData['disableTwoFactorCode']) {
            if ($this->totpAuthenticator->checkCode($user, $updateUserData['disableTwoFactorCode'])) {
                $user->setTotpEnabled(false);
                $user->setTotpSecret(null);
                $this->userService->updateUserObject($user);
            } else {
                $this->messageBag->add('error', 'error.2fa.code.invalid');
            }
        }

        return $user;
    }

    private function handeEmailAuth(array $updateUserData, UserInterface $user): UserInterface
    {
        if (isset($updateUserData['twoFactorCodeEmail']) && '' !== $updateUserData['twoFactorCodeEmail']) {
            if ($user->getEmailAuthCode() === $updateUserData['twoFactorCodeEmail']) {
                $user->setAuthCodeEmailEnabled(true);
                $this->userService->updateUserObject($user);
            } else {
                $this->messageBag->add('error', 'error.2fa.code.invalid');
            }
        }

        if (isset($updateUserData['disableTwoFactorCodeEmail']) && '' !== $updateUserData['disableTwoFactorCodeEmail']) {
            if ($user->getEmailAuthCode() === $updateUserData['disableTwoFactorCodeEmail']) {
                $user->setAuthCodeEmailEnabled(false);
                $user->setEmailAuthCode('');
                $this->userService->updateUserObject($user);
            } else {
                $this->messageBag->add('error', 'error.2fa.code.invalid');
            }
        }

        return $user;
    }
}
