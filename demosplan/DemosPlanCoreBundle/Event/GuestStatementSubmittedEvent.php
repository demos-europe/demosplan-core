<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

class GuestStatementSubmittedEvent extends DPlanEvent
{
    public function __construct(private readonly Statement $submittedStatement, private readonly string $emailText, private readonly string $emailAddress = '')
    {
    }

    public function getSubmittedStatement(): Statement
    {
        return $this->submittedStatement;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function getEmailText(): string
    {
        return $this->emailText;
    }

    public function hasEmailAddress(): bool
    {
        return '' !== $this->emailAddress;
    }
}
