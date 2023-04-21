<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use Countable;
use demosplan\DemosPlanCoreBundle\Constraint\StatementIdsInProcedureConstraint;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * Class StatementsInProcedureVO.
 *
 * @StatementIdsInProcedureConstraint(someNotFoundMessage="statements.in.procedure.someNotFound", noneFoundMessage="statements.in.procedure.noneFound")
 */
class StatementIdsInProcedureVO extends ValueObject implements Countable
{
    /**
     * @var string
     */
    protected $procedureId;

    /** @var string[] */
    protected $statementIds;

    /**
     * @param string   $procedureId
     * @param string[] $statementIds
     */
    public function __construct($procedureId, $statementIds)
    {
        $this->procedureId = $procedureId;
        $this->statementIds = $statementIds;
    }

    public function count(): int
    {
        if (\is_array($this->statementIds)) {
            return count($this->statementIds);
        }

        return 0;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    /**
     * @param string $procedureId
     */
    public function setProcedureId($procedureId)
    {
        $this->procedureId = $procedureId;
    }

    /**
     * @return string[]
     */
    public function getStatementIds(): array
    {
        return $this->statementIds;
    }

    /**
     * @param string[] $statementIds
     */
    public function setStatementIds(array $statementIds)
    {
        $this->statementIds = $statementIds;
    }
}
