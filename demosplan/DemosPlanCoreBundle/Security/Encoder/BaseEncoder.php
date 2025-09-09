<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Encoder;

use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

abstract class BaseEncoder implements LegacyPasswordHasherInterface
{
    public function verify(string $hashedPassword, string $plainPassword, string $salt = null): bool
    {
        return !$this->isPasswordTooLong($plainPassword) && $this->comparePasswords($hashedPassword, $this->hash($plainPassword, $salt));
    }

    protected function comparePasswords(string $password1, string $password2): bool
    {
        return hash_equals($password1, $password2);
    }

    protected function isPasswordTooLong(string $password): bool
    {
        return \strlen($password) > static::MAX_PASSWORD_LENGTH;
    }
}
