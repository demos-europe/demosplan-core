<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class StatementAlreadyConnectedToGdprConsentRevokeTokenException extends Exception
{
    public static function createFromStatementId(string $statementId): StatementAlreadyConnectedToGdprConsentRevokeTokenException
    {
        return new self("The statement with the ID {$statementId} is already connected to a token.");
    }
}
