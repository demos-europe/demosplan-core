<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\User;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * Class SaltedPassword.
 *
 * @method string getSalt()
 * @method string getHash()
 * @method void   setSalt(string $salt)
 * @method void setHash(string $hash);
 */
class HashedAndSaltedPassword extends ValueObject
{
    /**
     * @var string
     */
    protected $hash;

    /**
     * @var string
     */
    protected $salt;
}
