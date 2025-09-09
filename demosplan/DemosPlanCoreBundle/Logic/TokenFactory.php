<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Carbon\Carbon;

class TokenFactory
{
    public function createSaltedToken(string $base, int $length = 12): string
    {
        $salt = Carbon::now()->toIso8601String();

        return $this->createToken($base, $salt, $length);
    }

    protected function createToken(string $base, string $salt, int $length): string
    {
        return substr(hash('sha256', $base.$salt), 0, $length);
    }
}
