<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use demosplan\DemosPlanStatementBundle\ValueObject\StatementIdsInProcedureVO;
use Symfony\Component\Validator\Constraints as Assert;

class StatementBulkEditVO extends ValueObject
{
    /** @var string */
    protected $id;

    // @improve T12873
    /**
     * @Assert\Valid()
     *
     * @var StatementIdsInProcedureVO
     */
    protected $statementIdsInProcedure;

    /**
     * @var string
     *
     * @Assert\Length(min=1)
     */
    protected $recommendationAddition;

    /**
     * @Assert\Length(min=36, max=36)
     *
     * @var string
     */
    protected $assigneeId;

    /**
     * @param string[] $statementIds
     */
    public function __construct(string $procedureId, array $statementIds = [])
    {
        $this->statementIdsInProcedure = new StatementIdsInProcedureVO($procedureId, $statementIds);
    }

    public function getStatementIdsInProcedure(): StatementIdsInProcedureVO
    {
        return $this->statementIdsInProcedure;
    }

    public function setStatementIdsInProcedure(StatementIdsInProcedureVO $statementIdsInProcedure)
    {
        $this->statementIdsInProcedure = $statementIdsInProcedure;
    }

    /**
     * @return string|null
     */
    public function getRecommendationAddition()
    {
        return $this->recommendationAddition;
    }

    public function setRecommendationAddition(string $recommendationAddition)
    {
        $this->recommendationAddition = $recommendationAddition;
    }

    /**
     * @return string|null
     */
    public function getAssigneeId()
    {
        return $this->assigneeId;
    }

    public function setAssigneeId(string $assigneeId)
    {
        $this->assigneeId = $assigneeId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id)
    {
        $this->id = $id;
    }
}
