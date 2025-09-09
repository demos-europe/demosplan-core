<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\User;

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method array getUsers()
 * @method Orga  getOrganisation()
 */
class OrgaUsersPair extends ValueObject
{
    /**
     * @var array<int, User>
     */
    protected $users;

    /**
     * @var Orga
     */
    protected $organisation;

    /**
     * @param array<int, User> $users
     */
    public function __construct(array $users, Orga $organisation)
    {
        $this->users = $users;
        $this->organisation = $organisation;
        $this->lock();
    }
}
