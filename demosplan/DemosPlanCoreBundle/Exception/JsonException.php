<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class JsonException extends \JsonException
{
    public static function decodeFailed(): self
    {
        return new self('Failed to decode json: '.json_last_error_msg());
    }

    public static function encodeFailed(): self
    {
        return new self('Failed to encode to json: '.json_last_error_msg());
    }
}
