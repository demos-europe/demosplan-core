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

class AssessmentTableZipExportException extends DemosException
{
    public function __construct(private readonly string $level, string $userMsg, string $logMsg = '', int $code = 0)
    {
        parent::__construct($userMsg, $logMsg, $code);
    }

    public function getLevel(): string
    {
        return $this->level;
    }
}
