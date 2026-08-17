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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePairingCode;

/**
 * The persisted code plus the one moment its plaintext exists in the clear, grouped for transcription
 * (`abcd-efgh-jkmn`). Redeeming accepts the grouped and the bare form alike.
 */
class ProcedurePairingCodeIssueResult
{
    public function __construct(
        public readonly ProcedurePairingCode $code,
        public readonly string $plaintext,
    ) {
    }
}
