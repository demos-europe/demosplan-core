<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken;

use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;

/**
 * Return value of {@see PersonalAccessTokenService::create()}.
 *
 * The plaintext token is exposed to callers exactly once — at the moment of creation —
 * and is never persisted. Callers are expected to surface it to the end user immediately
 * and discard their reference.
 */
final readonly class PersonalAccessTokenCreationResult
{
    public function __construct(
        public PersonalAccessToken $token,
        public string $plaintext,
    ) {
    }
}
