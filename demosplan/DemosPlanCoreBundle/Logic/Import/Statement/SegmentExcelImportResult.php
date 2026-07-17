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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class SegmentExcelImportResult
{
    /**
     * @var Statement[]
     */
    private $statements = [];

    /**
     * Existing statements that received appended segments (attach mode).
     * Kept separate from newly created statements so they are flushed and indexed
     * but not reported or eventful as "created".
     *
     * @var Statement[]
     */
    private $updatedStatements = [];

    /**
     * @var Segment[]
     */
    private $segments = [];

    /**
     * @var ImportError[]
     */
    private $errors = [];

    /**
     * @var int
     */
    private $segmentCount = 0;

    /**
     * @var int
     */
    private $statementCount = 0;

    public function __construct()
    {
    }

    public function getStatements(): array
    {
        return $this->statements;
    }

    public function getSegments(): array
    {
        return $this->segments;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addStatement(Statement $statement): void
    {
        $this->statements[] = $statement;

        ++$this->statementCount;
    }

    /**
     * @return Statement[]
     */
    public function getUpdatedStatements(): array
    {
        return $this->updatedStatements;
    }

    public function addUpdatedStatement(Statement $statement): void
    {
        $this->updatedStatements[] = $statement;
    }

    public function addSegment(Segment $segment): void
    {
        $this->segments[] = $segment;

        ++$this->segmentCount;
    }

    public function getSegmentCount(): int
    {
        return $this->segmentCount;
    }

    public function getStatementCount(): int
    {
        return $this->statementCount;
    }

    public function addErrors(
        ConstraintViolationListInterface $violationList,
        int $currentLineNumber,
        string $currentWorksheetTitle,
    ): void {
        foreach ($violationList as $violation) {
            $this->errors[] = new ImportError($violation, $currentLineNumber, $currentWorksheetTitle);
        }
    }

    public function addError(
        string $message,
        int $currentLineNumber,
        string $currentWorksheetTitle,
    ): void {
        $this->errors[] = ImportError::fromMessage($message, $currentLineNumber, $currentWorksheetTitle);
    }

    public function hasErrors(): bool
    {
        return 0 < count($this->errors);
    }

    /**
     * @return array<int, array>
     */
    public function getErrorsAsArray(): array
    {
        $errorArray = [];
        foreach ($this->errors as $key => $error) {
            $errorArray[] = $error->toArray($key);
        }

        return $errorArray;
    }
}
