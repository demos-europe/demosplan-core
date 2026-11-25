<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Ocx;

use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;

use function in_array;

/**
 * Permissions of a request to the OCX intake API.
 *
 * The technical user has no role. Whether a project accepts statements or responses at all is
 * decided by the project enabling the feature permissions; everything else is denied.
 */
final readonly class OcxRequestContext implements ApiTokenContextInterface
{
    public const FEATURE_STATEMENT = 'feature_ocx_intake_statement';
    public const FEATURE_RESPONSE = 'feature_ocx_intake_response';

    public const FEATURE_PERMISSIONS = [
        self::FEATURE_STATEMENT,
        self::FEATURE_RESPONSE,
    ];

    public function allowsPermission(string $permission): bool
    {
        return in_array($permission, self::FEATURE_PERMISSIONS, true);
    }
}
