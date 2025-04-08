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

use demosplan\DemosPlanCoreBundle\Logic\ValidationLoggerInterface;
use demosplan\DemosPlanCoreBundle\Service\InputValidationService;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class InputValidationListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly InputValidationService $validationService,
        private readonly ValidationLoggerInterface $validationLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run early in the request lifecycle, before controller execution
            KernelEvents::REQUEST => ['onKernelRequest', 30],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Skip validation for specific routes if needed
        if ($this->shouldSkipValidation($request)) {
            return;
        }

        try {
            // Main validation logic
            $this->validationService->validateRequest($request);
        } catch (InvalidDataException $exception) {
            // Log validation failures
            $this->validationLogger->logValidationFailure($request, $exception);

            // Return appropriate error response
            $response = $this->createErrorResponse($exception);
            $event->setResponse($response);
        }
    }

    private function shouldSkipValidation(Request $request): bool
    {
        // Skip validation for assets, etc.
        $path = $request->getPathInfo();

        // Skip validation for certain paths
        if (preg_match('~^/(css|js|images|fonts)/~', $path)) {
            return true;
        }

        return false;
    }

    private function createErrorResponse(InvalidDataException $exception): Response
    {
        $statusCode = $exception->getStatusCode();

        // For API requests
        if ($this->isApiRequest($exception->getRequest())) {
            return new JsonResponse([
                'error' => [
                    'message' => 'Validation failed',
                    'code' => $statusCode,
                    // Don't expose detailed validation errors to prevent information disclosure
                ]
            ], $statusCode);
        }

        // For web requests, create HTML response
        return new Response(
            'Input validation failed. Please check your inputs and try again.',
            $statusCode
        );
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/')
            || $request->isXmlHttpRequest();
    }
}
