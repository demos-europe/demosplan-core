<?php declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Exception;

use demosplan\DemosPlanCoreBundle\Exception\DemosException;

class GdprConsentRevokeTokenAlreadyUsedException extends DemosException
{
    public static function createFromTokenValue(string $tokenValue): GdprConsentRevokeTokenAlreadyUsedException
    {
        return new self("Statement token with the token value {$tokenValue} was already used to revoke the GDPR consent from its associated statements.");
    }
}
