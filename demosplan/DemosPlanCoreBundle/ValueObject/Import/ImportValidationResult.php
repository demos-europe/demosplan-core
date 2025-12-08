<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Import;

use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Stores validation errors discovered during Pass 1 of Excel import.
 * Memory-efficient: only stores error messages, not full entities.
 */
class ImportValidationResult
{
    /**
     * @var array<int, array{lineNumber: int, worksheet: string, message: string}>
     */
    private array $errors = [];

    public function addError(string $message, int $lineNumber, string $worksheet): void
    {
        $this->errors[] = [
            'id'               => count($this->errors),
            'lineNumber'       => $lineNumber,
            'currentWorksheet' => $worksheet,
            'message'          => $message,
        ];
    }

    public function addErrors(ConstraintViolationListInterface $violations, int $lineNumber, string $worksheet): void
    {
        foreach ($violations as $violation) {
            $this->addError($violation->getMessage(), $lineNumber, $worksheet);
        }
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * @return array<int, array{id: int, lineNumber: int, currentWorksheet: string, message: string}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }
}
