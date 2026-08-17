<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;

/**
 * The persisted token plus the one and only chance to read its plaintext, which is never stored.
 */
final readonly class ProcedureIntegrationTokenCreationResult
{
    public function __construct(
        public ProcedureIntegrationToken $token,
        public string $plaintext,
    ) {
    }
}
