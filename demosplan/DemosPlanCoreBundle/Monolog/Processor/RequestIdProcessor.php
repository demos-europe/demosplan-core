<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Monolog\Processor;

use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds a unique request ID to log records to track all logs from a single request.
 */
class RequestIdProcessor implements ProcessorInterface
{
    private RequestStack $requestStack;
    private ?string $requestId = null;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function __invoke(array $record): array
    {
        // If we already generated an ID for this request instance, reuse it
        if (null !== $this->requestId) {
            $record['extra']['rid'] = $this->requestId;

            return $record;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            // If not in a request context (e.g., console), generate a shorter one-time ID
            $this->requestId = 'c'.base_convert(random_int(0, 1679615), 10, 36).base_convert(time(), 10, 36);
            $record['extra']['rid'] = $this->requestId;

            return $record;
        }

        // Check if request already has an ID (e.g., from header)
        if ($request->headers->has('X-Request-ID')) {
            $this->requestId = $request->headers->get('X-Request-ID');
        } else {
            // Generate a shorter, more concise ID (base36 encoding of timestamp+random)
            $this->requestId = base_convert(random_int(0, 1679615), 10, 36).base_convert(time(), 10, 36);
            // Store on request for middleware/controllers to access
            $request->attributes->set('request_id', $this->requestId);
        }

        $record['extra']['rid'] = $this->requestId;

        return $record;
    }
}
