<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Platform;

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ValueObject\EntrypointRoute;

/**
 * Interface to allow projects give their specific implementations.
 *
 * Interface EntryPointDeciderInterface
 */
interface EntryPointDeciderInterface
{
    public function determinePublicEntrypoint(): EntrypointRoute;

    public function determineEntryPointForUser(User $user): EntrypointRoute;
}
