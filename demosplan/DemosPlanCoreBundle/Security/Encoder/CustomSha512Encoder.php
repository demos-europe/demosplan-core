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

/**
 * Salt handling differed from symfony standard, therefore we need a custom sha512 implementation.
 */
class CustomSha512Encoder extends BaseEncoder
{
    public function hash(string $plainPassword, string $salt = null): string
    {
        return hash('sha512', $salt.$plainPassword);
    }

    public function needsRehash(string $hashedPassword): bool
    {
        // old md5 hashes need to be rehashed
        return 32 === strlen($hashedPassword);
    }
}
