<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Validates incoming requests for security threats and rejects malicious patterns.
 *
 * This listener runs VERY EARLY in the request lifecycle to:
 * - Detect and reject null byte injection attempts
 * - Prevent DoS attacks via oversized or deeply nested data
 * - Block known attack patterns (prototype pollution, directory traversal)
 *
 * Important: This listener DETECTS and REJECTS only. It never modifies request data.
 * Output escaping is handled by the view layer (Twig auto-escaping).
 */
class SecurityValidationListener implements EventSubscriberInterface
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
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run VERY EARLY (priority 512) before most other processing
            // This ensures security validation happens before any data processing
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip validation for static assets
        if ($this->shouldSkipValidation($request)) {
            return;
        }

        $threatType = $this->detectThreat($request);
        if (null === $threatType) {
            return;
        }

        // Answer directly instead of throwing: an exception would be logged as an error
        // (and reported to Sentry) and turned into a redirect to the 500 page by the
        // exception subscriber. The threat is already logged at warning level.
        $this->logThreat($request, $threatType);
        $event->setResponse(new Response('Bad Request', Response::HTTP_BAD_REQUEST));
    }

    private function detectThreat(Request $request): ?string
    {
        // Null bytes (injection attacks)
        if ($this->containsNullBytes($request)) {
            return 'null_byte_detected';
        }

        // DoS attempts (oversized data)
        if ($this->exceedsLimits($request)) {
            return 'dos_limits_exceeded';
        }

        // Known attack patterns
        if ($this->containsAttackPatterns($request)) {
            return 'attack_pattern_detected';
        }

        return null;
    }

    private function shouldSkipValidation(Request $request): bool
    {
        $path = $request->getPathInfo();

        // Skip validation for static assets and binary upload endpoints
        // TUS uploads contain binary data with legitimate null bytes
        return (bool) preg_match('~^/(css|js|images|fonts|_tus)/~', $path);
    }

    /**
     * Check ALL input sources for null bytes.
     *
     * This includes: query params, POST data, headers, cookies, file names, and raw body.
     */
    private function containsNullBytes(Request $request): bool
    {
        // Check query parameters, POST data, headers, and cookies
        if ($this->hasNullByteInData($request->query->all())
            || $this->hasNullByteInData($request->request->all())
            || $this->hasNullByteInData($request->headers->all())
            || $this->hasNullByteInData($request->cookies->all())) {
            return true;
        }

        // File names
        foreach ($request->files->all() as $file) {
            if ($file instanceof UploadedFile && str_contains($file->getClientOriginalName(), "\0")) {
                return true;
            }
        }

        // Raw body
        return str_contains($request->getContent(), "\0");
    }

    /**
     * Recursively check for null bytes in data structures.
     */
    private function hasNullByteInData(mixed $data): bool
    {
        if (is_string($data)) {
            return str_contains($data, "\0");
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Check array keys
                if (is_string($key) && str_contains($key, "\0")) {
                    return true;
                }
                // Check array values recursively
                if ($this->hasNullByteInData($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if request exceeds DoS protection limits.
     */
    private function exceedsLimits(Request $request): bool
    {
        $queryParams = $request->query->all();

        foreach ($queryParams as $key => $value) {
            // Parameter name too long
            if (strlen((string) $key) > self::MAX_PARAMETER_NAME_LENGTH) {
                return true;
            }

            // Parameter value too long
            if (is_string($value) && strlen($value) > self::MAX_PARAMETER_VALUE_LENGTH) {
                return true;
            }

            // Array too deeply nested
            if (is_array($value) && $this->getArrayDepth($value) > self::MAX_ARRAY_DEPTH) {
                return true;
            }

            // Array has too many elements
            if (is_array($value) && $this->countArrayElements($value) > self::MAX_ARRAY_ELEMENTS) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for known attack patterns.
     */
    private function containsAttackPatterns(Request $request): bool
    {
        $queryParams = $request->query->all();

        foreach ($queryParams as $key => $value) {
            // Prototype pollution
            if (in_array($key, self::PROTOTYPE_POLLUTION_KEYS, true)) {
                return true;
            }

            // Directory traversal
            if (is_string($value) && (str_contains($value, '../') || str_contains($value, '..\\'))) {
                return true;
            }
        }

        return false;
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
     * Log security threat for monitoring and incident response.
     */
    private function logThreat(Request $request, string $threatType): void
    {
        $this->logger->warning('Security validation rejected request', [
            'threat_type' => $threatType,
            'path'        => $request->getPathInfo(),
            'method'      => $request->getMethod(),
            'ip'          => $request->getClientIp(),
        ]);
    }
}
