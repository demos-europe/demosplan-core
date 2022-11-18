<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class AdditionalStatementDataEvent extends DPlanEvent
{
    private Statement $statement;

    /**
     * The array containing all the data to update a given statement.
     * Subscribers need to check for the existence of relevant keys.
     * @var array<string, mixed>
     */
    private array $data;

    public function __construct(Statement $statement, array $data)
    {
        $this->statement = $statement;
        $this->data = $data;
    }

    /**
     * @return Statement
     */
    public function getStatement(): Statement
    {
        return $this->statement;
    }

    /**
     * @param Statement $statement
     */
    public function setStatement(Statement $statement): void
    {
        $this->statement = $statement;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
