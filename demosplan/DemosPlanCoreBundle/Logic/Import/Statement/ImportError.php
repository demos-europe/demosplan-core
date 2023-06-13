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
    /**
     * @var int
     */
    private $lineNumber;

    /**
     * @var ConstraintViolation
     */
    private $violation;

    /**
     * @var string
     */
    private $worksheetTitle;

    public function __construct(ConstraintViolation $violation, int $lineNumber, string $worksheetTitle = '')
    {
        $this->violation = $violation;
        $this->lineNumber = $lineNumber;
        $this->worksheetTitle = $worksheetTitle;
    }

    public function getLine(): int
    {
        return $this->getLineNumber();
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

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
