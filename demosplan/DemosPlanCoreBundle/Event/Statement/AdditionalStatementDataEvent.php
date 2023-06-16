<?php


declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;

class AdditionalStatementDataEvent extends DPlanEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private Statement $statement,
        /**
        * The array containing all the data to update a given statement.
        Subscribers need to check for the existence of relevant keys.
        *
        */
        private array $data
    )
    {
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

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
