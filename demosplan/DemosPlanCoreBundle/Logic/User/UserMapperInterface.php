<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;

interface UserMapperInterface
{
    /**
     * Try to get valid user for given credentials. May not be possible depending on the
     * authenticator.
     */
    public function getValidUser(Credentials $credentials): ?User;
}
