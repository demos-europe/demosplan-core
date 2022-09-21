<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Exception;

use Exception;

class PreNewProcedureCreatedEventConcernException extends Exception
{
    /** @var string[] */
    protected $messages = [];

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param string[] $messages
     */
    public function setMessages(array $messages)
    {
        $this->messages = $messages;
    }
}
