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
     * @var Segment[]
     */
    private $segments = [];

    /**
     * @var ImportError[]
     */
    private $errors = [];

    /**
     * Non-blocking notices, e.g. a row skipped for a duplicate Eingangsnummer - unlike errors, these
     * never abort the import, but the user still needs to see them to know not everything they
     * submitted ended up as a statement.
     *
     * @var ImportError[]
     */
    private $warnings = [];

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

    public function addWarning(
        string $message,
        int $currentLineNumber,
        string $currentWorksheetTitle,
    ): void {
        $this->warnings[] = ImportError::fromMessage($message, $currentLineNumber, $currentWorksheetTitle);
    }

    public function hasWarnings(): bool
    {
        return 0 < count($this->warnings);
    }

    /**
     * @return array<int, array>
     */
    public function getWarningsAsArray(): array
    {
        $warningArray = [];
        foreach ($this->warnings as $key => $warning) {
            $warningArray[] = $warning->toArray($key);
        }

        return $warningArray;
    }
}
