<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use Exception;

class MessageBagException extends Exception
{
    public static function severityNotSupportedException($severity)
    {
        $severityList = implode(', ', MessageBag::$definedSeverities);

        return new self("Severity {$severity} is not supported. Please choose one from: {$severityList}");
    }

    public static function messageMustBeStringException()
    {
        return new self('Message must be a string and should be a translation key.');
    }
}
