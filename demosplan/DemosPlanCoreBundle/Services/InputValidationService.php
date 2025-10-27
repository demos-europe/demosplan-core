<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services;

use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\NullByteDetectedException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiRequestValidator;
use demosplan\DemosPlanCoreBundle\Validator\InputValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class InputValidationService
{
    /**
     * Maximum allowed length for query parameter names.
     * Prevents DoS attacks via excessively long parameter names.
     */
    private const MAX_PARAMETER_NAME_LENGTH = 500;

    /**
     * Maximum allowed length for string query parameter values.
     * Prevents DoS attacks via excessively long parameter values.
     */
    private const MAX_PARAMETER_VALUE_LENGTH = 50000;

    /**
     * Maximum allowed nesting depth for array query parameters.
     * Prevents DoS attacks via deeply nested array structures.
     */
    private const MAX_ARRAY_DEPTH = 20;

    /**
     * Maximum total number of elements in nested array query parameters.
     * Prevents DoS attacks via large array structures.
     */
    private const MAX_ARRAY_ELEMENTS = 5000;

    /**
     * Parameter names that indicate prototype pollution attacks.
     * These should never appear in legitimate query parameters.
     */
    private const PROTOTYPE_POLLUTION_KEYS = ['__proto__', 'constructor', 'prototype'];

    public function __construct(
        private readonly JsonApiRequestValidator $jsonApiValidator,
        private readonly InputValidator $inputValidator,
    ) {
    }

    /**
     * Validate a request by checking various aspects
     * - Content type validation
     * - Input validation and HTML escaping
     * - Request parameters validation
     * - JSON structure validation
     *
     * @throws InvalidDataException when validation fails or null bytes are detected
     */
    public function validateRequest(Request $request): void
    {
        try {
            // 1. Basic validation based on request type
            if ($this->jsonApiValidator->isApiRequest($request)) {
                $response = $this->jsonApiValidator->validateJsonApiRequest($request);
                if (null !== $response) {
                    throw new InvalidDataException('Invalid JSON:API request', $request, $response->getStatusCode());
                }
            }

            // 2. Validate and escape query parameters
            $this->validateQueryParameters($request);

            // 3. Validate and escape request body
            $this->validateRequestBody($request);
        } catch (NullByteDetectedException $e) {
            // Null bytes detected - reject the request
            throw new InvalidDataException(
                'Request rejected: Null byte detected in input. This is a potential security threat.',
                $request,
                400
            );
        }
    }

    private function validateQueryParameters(Request $request): void
    {
        $queryParams = $request->query->all();

        foreach ($queryParams as $key => $value) {
            // Validate and escape each parameter
            $processedValue = $this->inputValidator->validateAndEscape($value);
            $request->query->set($key, $processedValue);

            // Additional validation logic as needed
            if (!$this->isValidQueryParam($key, $processedValue)) {
                throw new InvalidDataException("Invalid query parameter: $key", $request);
            }
        }
    }

    private function validateRequestBody(Request $request): void
    {
        $content = $request->getContent();

        if (empty($content)) {
            return;
        }

        // For JSON content
        if ($this->isJsonContentType($request)) {
            $this->validateJsonContent($request, $content);
            return;
        }

        // For form data
        if ($this->isFormContentType($request)) {
            $this->validateFormData($request);
        }

    }

    private function validateJsonContent(Request $request, string $content): void
    {
        $jsonData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidDataException('Invalid JSON format', $request);
        }

        // Recursive validation and escaping of JSON data
        $this->validateAndEscapeRecursively($jsonData);
    }

    private function validateFormData(Request $request): void
    {
        $requestData = $request->request->all();
        $files = $request->files->all();

        // Validate and escape form fields
        foreach ($requestData as $key => $value) {
            $processedValue = $this->inputValidator->validateAndEscape($value);
            $request->request->set($key, $processedValue);
        }

        // Validate file uploads
        foreach ($files as $key => $file) {
            if (!$this->isValidFile($file)) {
                throw new InvalidDataException("Invalid uploaded file: $key", $request);
            }
        }
    }

    private function validateAndEscapeRecursively($data)
    {
        if (is_array($data)) {
            return array_map(function ($value)
            {
                return $this->validateAndEscapeRecursively($value);
            }, $data);
        }

        return $this->inputValidator->validateAndEscape($data);
    }

    private function isJsonContentType(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type', '');
        return str_contains($contentType, 'application/json') ||
               str_contains($contentType, 'application/vnd.api+json');
    }

    private function isFormContentType(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type', '');
        return str_contains($contentType, 'application/x-www-form-urlencoded') ||
               str_contains($contentType, 'multipart/form-data');
    }

    /**
     * Validates query parameter for critical security threats.
     *
     * Uses conservative checks with high thresholds to avoid false positives.
     * Only rejects clearly malicious patterns that should never appear in legitimate requests.
     *
     * @param string $key Parameter name
     * @param mixed $value Parameter value (already HTML-escaped)
     * @return bool True if parameter is valid, false if invalid/suspicious
     */
    private function isValidQueryParam(string $key, mixed $value): bool
    {
        // 1. DoS Protection - very high limits to avoid false positives

        // Reject excessively long parameter names
        if (strlen($key) > self::MAX_PARAMETER_NAME_LENGTH) {
            return false;
        }

        // Reject prototype pollution attempts (JavaScript object manipulation)
        // These should never appear in legitimate query parameters
        if (in_array($key, self::PROTOTYPE_POLLUTION_KEYS, true)) {
            return false;
        }

        // 2. Value validation

        if (is_string($value)) {
            // Reject extremely long strings (DoS protection)
            if (strlen($value) > self::MAX_PARAMETER_VALUE_LENGTH) {
                return false;
            }

            // Reject directory traversal patterns
            // Legitimate use cases for "../" in query params are extremely rare
            if (str_contains($value, '../') || str_contains($value, '..\\')) {
                return false;
            }
        }

        if (is_array($value)) {
            // Reject deeply nested arrays (DoS protection)
            if ($this->getArrayDepth($value) > self::MAX_ARRAY_DEPTH) {
                return false;
            }

            // Reject arrays with excessive total elements (DoS protection)
            if ($this->countArrayElements($value) > self::MAX_ARRAY_ELEMENTS) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate maximum nesting depth of an array.
     */
    private function getArrayDepth(array $array): int
    {
        $maxDepth = 1;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                }
            }
        }

        return $maxDepth;
    }

    /**
     * Count total elements in nested array structure.
     */
    private function countArrayElements(array $array): int
    {
        $count = count($array);

        foreach ($array as $value) {
            if (is_array($value)) {
                $count += $this->countArrayElements($value);
            }
        }

        return $count;
    }

    /**
     * Validates an uploaded file using Symfony's built-in validation.
     *
     * This performs basic validation checks:
     * - File was uploaded successfully (no upload errors)
     * - File size within PHP limits
     * - File is readable
     *
     * Note: This is lightweight validation. Heavy validation (virus scanning,
     * MIME type verification) is performed later by FileUploadService.
     *
     * @param mixed $file Uploaded file
     * @return bool True if file is valid, false otherwise
     */
    private function isValidFile(mixed $file): bool
    {
        // Only validate UploadedFile instances
        if (!$file instanceof UploadedFile) {
            return false;
        }

        // Use Symfony's built-in validation
        // This checks for upload errors, size limits, and file existence
        return $file->isValid();
    }
}
