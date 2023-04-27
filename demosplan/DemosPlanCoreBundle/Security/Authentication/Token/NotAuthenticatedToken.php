<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Token;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * This class exists only to be sure that `CurrentUserService::getToken()` always
 * returns a `TokenInterface`.
 */
class NotAuthenticatedToken extends AbstractToken
{
    public function getCredentials(): array
    {
        return [];
    }
}
