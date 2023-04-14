<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use LogicException;

class AssessmentExportOptionsException extends LogicException
{
    public static function missingSectionException($sectionName)
    {
        return new self("Missing options for {$sectionName}.");
    }

    public static function noDefaultsException()
    {
        return new self('No default export options were specified.');
    }

    public static function overridingDisabledDefaultsInSectionException($section, $option)
    {
        return new self(
            "The defaults for {$option} are disabled in the current configuration and can thus not be overriden for {$section}"
        );
    }

    public static function undefinedOptionNameException($optionName, array $availableOptions)
    {
        $availableOptions = implode(', ', $availableOptions);

        return new self(
            "The option {$optionName} is not defined in the defaults, required options are: {$availableOptions}."
        );
    }

    public static function undefinedSectionNameException($sectionName)
    {
        return new self("The section '{$sectionName}' is not defined.'");
    }
}
