<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use Symfony\Component\Validator\ConstraintViolationListInterface;

abstract class AbstractStatementFromRowBuilder
{
    protected StatementInterface $statement;

    public function __construct()
    {
        $this->statement = new Statement();
    }

    abstract public function setExternId(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setText(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setPlanningDocumentCategoryTitle(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setOrgaName(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setDepartmentName(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setAuthorName(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setSubmitterName(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setSubmiterEmailAddress(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setSubmitterStreetName(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setSubmitterHouseNumber(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setSubmitterPostalCode(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setSubmitterCity(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setSubmitDate(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setAuthoredDate(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setInternId(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function getInternId(): ?string;

    abstract public function getExternId(): string;

    abstract public function setMemo(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setFeedback(Cell $cell): ?ConstraintViolationListInterface;

    abstract public function setNumberOfAnonymVotes(Cell $cell): ?ConstraintViolationListInterface;

    /**
     * Returns the statement that was created and filled since the last call of this method or a list of violations
     * due to invalid values/state of the statement.
     */
    abstract public function buildStatementAndReset(): StatementInterface|ConstraintViolationListInterface;

    abstract public function resetStatement(): void;
}
