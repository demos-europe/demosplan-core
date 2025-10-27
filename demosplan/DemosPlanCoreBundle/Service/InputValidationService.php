<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Service;

use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\NullByteDetectedException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiRequestValidator;
use demosplan\DemosPlanCoreBundle\Validator\InputValidator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class InputValidationService
{
    public function __construct(
        private readonly JsonApiRequestValidator $jsonApiValidator,
        private readonly InputValidator $inputValidator,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Validate a request by checking various aspects
     * - Content type validation
     * - Content sanitization
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

            // 2. Sanitize and validate query parameters
            $this->validateQueryParameters($request);

            // 3. Sanitize and validate request body
            $this->validateRequestBody($request);

            // 4. Store sanitized content back to request for further processing
            $this->requestStack->getCurrentRequest()?->attributes->set('validated', true);
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
            if ($this->isInvalidQueryParam($key, $processedValue)) {
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
            return;
        }

        // For other content types, general sanitization
        // ...
    }

    private function validateJsonContent(Request $request, string $content): void
    {
        $jsonData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new InvalidDataException('Invalid JSON format', $request);
        }

        // Recursive validation and escaping of JSON data
        $processedData = $this->validateAndEscapeRecursively($jsonData);

        // Set processed data back to request
        $request->attributes->set('sanitized_json', $processedData);
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

    private function isInvalidQueryParam($key, $value): bool
    {
        // Implement query parameter validation logic
        return false;
    }

    private function isValidFile($file): bool
    {
        // Implement file validation logic
        return true;
    }
}
