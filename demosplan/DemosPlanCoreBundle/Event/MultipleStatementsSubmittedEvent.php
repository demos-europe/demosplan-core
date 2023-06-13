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

class MultipleStatementsSubmittedEvent extends DPlanEvent
{
    /**
     * @var array<int, Statement>
     */
    private $submittedStatements;

    /**
     * @var bool
     */
    private $public;

    /**
     * @param array<int, Statement> $submittedStatements
     */
    public function __construct(array $submittedStatements, bool $public)
    {
        $this->submittedStatements = $submittedStatements;
        $this->public = $public;
    }

    /**
     * @return array<int, Statement>
     */
    public function getSubmittedStatements(): array
    {
        return $this->submittedStatements;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }
}
