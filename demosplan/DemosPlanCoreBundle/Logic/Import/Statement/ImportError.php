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

use Symfony\Component\Validator\ConstraintViolation;

class ImportError
{
    public function __construct(private readonly ConstraintViolation $violation, private readonly int $lineNumber, private readonly string $worksheetTitle = '')
    {
    }

    public function getLine(): int
    {
        return $this->getLineNumber();
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    /**
     * @return array{id: int, currentWorksheet: string, lineNumber: int, message: string}
     */
    public function toArray(int $key): array
    {
        return
            [
                'id'               => $key,
                'currentWorksheet' => $this->getWorksheetTitle(),
                'lineNumber'       => $this->getLineNumber(),
                'message'          => $this->getMessage(),
            ];
    }

    public function getMessage(): string
    {
        return $this->violation->getMessage();
    }

    public function getWorksheetTitle(): string
    {
        return $this->worksheetTitle;
    }

    public function getViolation(): ConstraintViolation
    {
        return $this->violation;
    }
}
