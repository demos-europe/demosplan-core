<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementSimilarity;

class StatementActionEvent extends DPlanEvent
{
    /**
     * @var Statement
     */
    protected $statement;

    /**
     * @var StatementSimilarity[]|null
     */
    protected $statementSimilarities;

    public function __construct(Statement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * @return Statement
     */
    public function getStatement()
    {
        return $this->statement;
    }

    /**
     * @return StatementSimilarity[]|null
     */
    public function getStatementSimilarities()
    {
        return $this->statementSimilarities;
    }

    /**
     * @param StatementSimilarity[] $statementSimilarities
     */
    public function setStatementSimilarities(array $statementSimilarities)
    {
        $this->statementSimilarities = $statementSimilarities;
    }

    public function getSimilaritiesAsString(): string
    {
        $string = '';
        foreach ($this->getStatementSimilarities() as $similarity) {
            $string .= $similarity."\n";
        }

        return $string;
    }
}
