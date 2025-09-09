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

class SaltlessMd5Encoder extends BaseEncoder
{
    public function hash(string $plainPassword, string $salt = null): string
    {
        return hash('md5', $plainPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        // nothing needs to be rehashed into md5
        return false;
    }
}
