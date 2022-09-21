<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanPluginBundle\Exception;

class PluginException extends \RuntimeException
{
    public static function invalidComposerJsonException($path)
    {
        return new self("Invalid JSON file: $path - ".json_last_error_msg());
    }
}
