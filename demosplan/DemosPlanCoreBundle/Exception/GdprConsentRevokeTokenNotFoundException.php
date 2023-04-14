<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class GdprConsentRevokeTokenNotFoundException extends ResourceNotFoundException
{
    public static function createFromTokenValue(string $tokenValue): GdprConsentRevokeTokenNotFoundException
    {
        return new self("No token with the token value {$tokenValue} exists.");
    }

    public static function createFromStatementId(string $statementId): GdprConsentRevokeTokenNotFoundException
    {
        return new self("No token for the statement ID {$statementId} found.");
    }

    public static function createFromTokenValueAndEmailAddress(string $tokenValue, string $emailAddress): GdprConsentRevokeTokenNotFoundException
    {
        return new self("No token with the token value {$tokenValue} and email address {$emailAddress} exists.");
    }
}
