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

class UnexpectedWorksheetNameException extends Exception
{
    /**
     * @param string[] $expectedTitles
     */
    public function __construct(private readonly string $incomingTitle, private readonly array $expectedTitles)
    {
        parent::__construct();
    }

    public function getIncomingTitle(): string
    {
        return $this->incomingTitle;
    }

    public function getExpectedTitles(): string
    {
        return implode(', ', $this->expectedTitles);
    }
}
