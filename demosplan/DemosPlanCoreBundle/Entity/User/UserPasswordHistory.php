<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\User;

use DateTime;

class UserPasswordHistory
{
    private string $id;
    private User $user;
    private string $hashedPassword;
    private DateTime $createdDate;

    public function __construct(User $user, string $hashedPassword)
    {
        $this->id = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->user = $user;
        $this->hashedPassword = $hashedPassword;
    }

    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCreatedDate(): DateTime
    {
        return $this->createdDate;
    }
}
