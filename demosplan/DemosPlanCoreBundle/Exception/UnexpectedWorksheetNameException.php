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
     * @var string
     */
    private $incomingTitle;

    /**
     * @var array<int, string>
     */
    private $expectedTitles;

    public function __construct(string $incomingTitle, array $expectedTitles)
    {
        $this->incomingTitle = $incomingTitle;
        $this->expectedTitles = $expectedTitles;
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
