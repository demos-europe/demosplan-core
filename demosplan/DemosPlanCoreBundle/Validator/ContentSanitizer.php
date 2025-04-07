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

class ContentSanitizer
{
    /**
     * Sanitize input data based on type
     */
    public function sanitize($input)
    {
        if (is_string($input)) {
            return $this->sanitizeString($input);
        }
        
        if (is_array($input)) {
            return $this->sanitizeArray($input);
        }
        
        if (is_numeric($input) || is_bool($input) || is_null($input)) {
            return $input; // Primitive types that don't need sanitization
        }
        
        // If we can't sanitize, return as is but log it
        return $input;
    }
    
    private function sanitizeString(string $input): string
    {
        // Remove null bytes, which can be used in injection attacks
        $sanitized = str_replace("\0", '', $input);
        
        // Prevent HTML injection
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Additional sanitization as needed
        return $sanitized;
    }
    
    private function sanitizeArray(array $input): array
    {
        $result = [];
        
        foreach ($input as $key => $value) {
            // Sanitize keys too
            $sanitizedKey = is_string($key) ? $this->sanitizeString($key) : $key;
            $result[$sanitizedKey] = $this->sanitize($value);
        }
        
        return $result;
    }
}
