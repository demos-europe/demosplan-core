<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use RuntimeException;

class MisconfiguredException extends RuntimeException
{
    public static function missingParameters(): self
    {
        return new self('This plugin only works with ai_service_salt and ai_service_post_url configured.');
    }
}
