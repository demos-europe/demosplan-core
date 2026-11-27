<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Types;

use demosplan\DemosPlanCoreBundle\Security\Ocx\OcxRequestContext;

/**
 * The `kind` of an OCX entry this receiver understands.
 */
enum OcxEntryKind: string
{
    case STATEMENT = 'statement';
    case RESPONSE = 'response';

    /**
     * The feature permission that allows delivering entries of this kind.
     */
    public function getPermission(): string
    {
        return match ($this) {
            self::STATEMENT => OcxRequestContext::FEATURE_STATEMENT,
            self::RESPONSE  => OcxRequestContext::FEATURE_RESPONSE,
        };
    }
}
