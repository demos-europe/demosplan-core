<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\ApiToken;

/**
 * A freshly generated token: everything the caller needs to persist the record and hand the
 * plaintext to the user exactly once.
 *
 * {@see self::$secret} and {@see self::$fullToken} are never persisted — only {@see self::$prefix}
 * (for lookup) and {@see self::$hash} are.
 */
final readonly class GeneratedApiToken
{
    public function __construct(
        public string $prefix,
        public string $secret,
        public string $hash,
        public string $fullToken,
    ) {
    }
}
