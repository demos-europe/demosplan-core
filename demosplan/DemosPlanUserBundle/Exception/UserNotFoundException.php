<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;

class UserNotFoundException extends ResourceNotFoundException
{
    /**
     * @var string|null
     */
    private $userLogin;

    public static function createFromId(string $userId): self
    {
        return new self("Could not find User with ID {$userId}");
    }

    public static function createFromLogin(string $login): self
    {
        $self = new self("Could not find User with Login {$login}");
        $self->userLogin = $login;

        return $self;
    }

    public function getUserLogin(): ?string
    {
        return $this->userLogin;
    }
}
