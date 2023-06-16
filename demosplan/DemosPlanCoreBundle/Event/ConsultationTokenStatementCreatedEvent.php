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
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementCreatedEvent;

class ConsultationTokenStatementCreatedEvent extends StatementCreatedEvent
{
    public function __construct(Statement $statement, private readonly string $tokenNote)
    {
        parent::__construct($statement);
    }

    public function getTokenNote(): string
    {
        return $this->tokenNote;
    }
}
