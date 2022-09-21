<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\Exception;


use RuntimeException;

class MaillaneApiException extends RuntimeException
{
    public static function interactionFailed($url): self
    {
        return new self("Interaction with Maillane failed for endpoint: {$url}");
    }
}
