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
use UnexpectedValueException;

class SendMailException extends UnexpectedValueException
{
    public function __construct($message, protected $context = null, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param null $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }

    public static function stopProcessingMailList(): SendMailException
    {
        return new self('An error occured during processing a list of mails to be send; aborting.');
    }

    public static function mailListFailed($to, $originalException): SendMailException
    {
        $numberOfMailsToBeSend = is_countable($to) ? count($to) : 0;

        return new self(
            'Failed to store one or multiple mails to be send in database.'.
            "Rolled back, none of the {$numberOfMailsToBeSend} mails were stored.",
            null,
            0,
            $originalException
        );
    }
}
