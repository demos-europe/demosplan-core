<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use demosplan\DemosPlanCoreBundle\Exception\NullByteDetectedException;

/**
 * Validates input for security threats and escapes HTML entities.
 *
 * This class performs two operations:
 * 1. Validation - Detects security threats (null bytes) and rejects input by throwing exceptions
 * 2. Escaping - Transforms safe input by escaping HTML entities to prevent XSS
 */
class InputValidator
{
    /**
     * Validates input for security threats and escapes HTML entities.
     *
     * @param mixed $input The input to validate and escape
     * @return mixed The validated and escaped input
     * @throws NullByteDetectedException when null bytes are detected
     */
    public function validateAndEscape($input)
    {
        if (is_string($input)) {
            return $this->validateAndEscapeString($input);
        }

        if (is_array($input)) {
            return $this->validateAndEscapeArray($input);
        }

        // Primitive types that don't need validation or escaping

        return $input;
    }

    /**
     * Validates a string for security threats and escapes HTML entities.
     *
     * @throws NullByteDetectedException when null bytes are detected
     */
    private function validateAndEscapeString(string $input): string
    {
        // Validate: Detect null bytes and reject
        if (str_contains($input, "\0")) {
            throw new NullByteDetectedException('Null byte detected in input string');
        }

        // Escape: Prevent HTML injection
        $escaped = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $escaped;
    }

    /**
     * Validates array keys and values for security threats and escapes HTML entities.
     *
     * @throws NullByteDetectedException when null bytes are detected in keys or values
     */
    private function validateAndEscapeArray(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            // Validate and escape keys too
            $processedKey = is_string($key) ? $this->validateAndEscapeString($key) : $key;
            $result[$processedKey] = $this->validateAndEscape($value);
        }

        return $result;
    }
}
