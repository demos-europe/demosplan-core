<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;

class HandlerException extends Exception
{
    public static function fragmentExportFailedException($format)
    {
        return new self("Exporting statement fragments as {$format} failed.");
    }

    public static function assessmentExportFailedException($format)
    {
        return new self("Exporting the assessment table as {$format} failed.");
    }

    public static function tooManyColumnDefinitionsException()
    {
        return new self('Cannot export excel document with more than 26 column definitions');
    }
}
