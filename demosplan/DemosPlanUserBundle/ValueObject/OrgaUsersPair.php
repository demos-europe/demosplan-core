<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\ValueObject;

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
    protected array $users;

    protected Orga $organisation;

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
