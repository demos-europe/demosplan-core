<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

/**
 * Service for sanitizing HTTP headers to prevent header injection attacks.
 */
class HeaderSanitizerService
{
    /**
     * Sanitize a general header value to prevent header injection.
     *
     * @param string $header The header value to sanitize
     *
     * @return string The sanitized header value
     */
    public function sanitizeHeader(string $header): string
    {
        // Remove control characters, including NULL bytes
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', $header);

        // Remove everything after newlines to prevent header injection
        if (str_contains($sanitized, "\r") || str_contains($sanitized, "\n")) {
            $parts = preg_split('/[\r\n]+/', $sanitized);

            return $parts[0];
        }

        return $sanitized;
    }

    /**
     * Sanitize an authorization header value (specific rules for auth tokens).
     *
     * @param string $header The authorization header value to sanitize
     *
     * @return string The sanitized authorization header value
     */
    public function sanitizeAuthHeader(string $header): string
    {
        // First apply general sanitization
        $sanitized = $this->sanitizeHeader($header);

        // Only allow alphanumeric characters and a limited set of special characters commonly used in tokens
        // We specifically exclude < and > and other characters that could be used for XSS
        $sanitized = preg_replace('/<[^>]*>/', '', $sanitized); // Remove anything between < and >

        return preg_replace('/[^a-zA-Z0-9 \-_\.~\+\/=]/', '', $sanitized);
    }

    /**
     * Sanitize a CSRF token value (stricter rules for CSRF tokens).
     *
     * @param string $token The CSRF token value to sanitize
     *
     * @return string The sanitized CSRF token value
     */
    public function sanitizeCsrfToken(string $token): string
    {
        // First apply general sanitization
        $sanitized = $this->sanitizeHeader($token);

        // Strip all HTML tags first
        $sanitized = preg_replace('/<[^>]*>/', '', $sanitized);

        // Only allow alphanumeric characters and a limited set of special characters for CSRF tokens
        return preg_replace('/[^a-zA-Z0-9\-_]/', '', $sanitized);
    }

    /**
     * Sanitize an origin header value.
     *
     * @param string $origin The origin header value to sanitize
     *
     * @return string The sanitized origin header value
     */
    public function sanitizeOrigin(string $origin): string
    {
        // First apply general sanitization
        $sanitized = $this->sanitizeHeader($origin);

        // URL validation (allow only http/https origins with valid hostname characters)
        if (!preg_match('/^https?:\/\/[a-zA-Z0-9\-\.]+(\:[0-9]+)?$/', $sanitized)) {
            // If not a valid origin, return empty string for safety
            return '';
        }

        return $sanitized;
    }
}
