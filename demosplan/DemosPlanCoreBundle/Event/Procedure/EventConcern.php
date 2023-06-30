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
    /** @var string */
    protected $message;

    /**
     * @param null $exception
     */
    public function __construct(string $message, protected $exception = null)
    {
        $this->message = $message;
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
