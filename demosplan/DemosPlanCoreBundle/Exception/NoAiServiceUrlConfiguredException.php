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

class NoAiServiceUrlConfiguredException extends Exception
{
    /**
     * @return static
     */
    public static function create(): self
    {
        return new self('No URL configured to send request for statements to be segmented to the AI service');
    }
}
