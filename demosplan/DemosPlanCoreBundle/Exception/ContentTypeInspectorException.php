<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use LogicException;

class ContentTypeInspectorException extends LogicException
{
    public static function emptyContentType(): self
    {
        return new self('Cannot inspect an empty Content-Type.');
    }

    public static function invalidContentType(): self
    {
        return new self('Content-Type is not a valid IANA Media Type.');
    }
}
