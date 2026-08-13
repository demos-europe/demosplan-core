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

    /**
     * Create ImportError from a plain string message (used in two-pass validation).
     */
    public static function fromMessage(string $message, int $lineNumber, string $worksheetTitle): self
    {
        // Create minimal ConstraintViolation for compatibility
        $violation = new ConstraintViolation(
            $message,
            null,
            [],
            null,
            '',
            null
        );

        return new self($violation, $lineNumber, $worksheetTitle);
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
     * @return array{id: int, currentWorksheet: string, lineNumber: int, message: string, field: string, invalidValue: bool|float|int|string}
     */
    public function toArray(int $key): array
    {
        // getInvalidValue() may return an entity/array (e.g. when a whole statement
        // fails validation); reduce non-scalars to their type so the result stays
        // safely serializable for both the log context and the JSON error response.
        $invalidValue = $this->violation->getInvalidValue();

        return
            [
                'id'               => $key,
                'currentWorksheet' => $this->getWorksheetTitle(),
                'lineNumber'       => $this->getLineNumber(),
                'message'          => $this->getMessage(),
                'field'            => $this->violation->getPropertyPath(),
                'invalidValue'     => is_scalar($invalidValue) ? $invalidValue : get_debug_type($invalidValue),
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
