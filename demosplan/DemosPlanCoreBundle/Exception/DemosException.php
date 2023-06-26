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

use Exception;

/**
 * Gives support to specify the frontend error message to show to the user.
 * It is expected to be a label frm the translation files.
 *
 * Class DemosCoreException
 */
class DemosException extends Exception
{
    /**
     * @var string
     */
    private $userMsg;

    public function __construct(string $userMsg, string $logMsg = '', int $code = 0)
    {
        parent::__construct($logMsg, $code);
        $this->userMsg = $userMsg;
    }

    public function getUserMsg(): string
    {
        return $this->userMsg;
    }
}
