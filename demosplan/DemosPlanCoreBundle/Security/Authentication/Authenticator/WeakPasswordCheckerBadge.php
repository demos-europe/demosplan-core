<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

class WeakPasswordCheckerBadge implements BadgeInterface
{
    public function __construct(private readonly string $password)
    {
    }

    public function isResolved(): bool
    {
        return true;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
