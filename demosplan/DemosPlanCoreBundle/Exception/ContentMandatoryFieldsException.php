<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Exception;
use Throwable;

class ContentMandatoryFieldsException extends Exception
{
    /** @var array<int, string> */
    private $mandatoryFieldMessages;

    public function __construct(array $mandatoryFieldMessages, $message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->mandatoryFieldMessages = $mandatoryFieldMessages;
    }

    /**
     * @return array<int, string>
     */
    public function getMandatoryFieldMessages(): array
    {
        return $this->mandatoryFieldMessages;
    }
}
