<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Procedure;

use Exception;

class EventConcern
{
    /** @var Exception|null */
    protected $exception;
    /** @var string */
    protected $message;

    /**
     * @param null $exception
     */
    public function __construct(string $message, $exception = null)
    {
        $this->message = $message;
        $this->exception = $exception;
    }

    public function getException(): ?Exception
    {
        return $this->exception;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
