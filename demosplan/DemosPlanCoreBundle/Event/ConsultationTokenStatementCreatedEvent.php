<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementCreatedEvent;

class ConsultationTokenStatementCreatedEvent extends StatementCreatedEvent
{
    /**
     * @var string
     */
    private $tokenNote;

    public function __construct(Statement $statement, string $tokenNote)
    {
        parent::__construct($statement);

        $this->tokenNote = $tokenNote;
    }

    public function getTokenNote(): string
    {
        return $this->tokenNote;
    }
}
