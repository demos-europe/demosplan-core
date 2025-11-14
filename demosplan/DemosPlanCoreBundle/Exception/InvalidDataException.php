<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class InvalidDataException extends DemosException
{
    /**
     * Stores the HTTP request that triggered this invalid data exception.
     * Used for context tracking and debugging purposes.
     * May be null when exception occurs outside HTTP request context.
     */
    private ?Request $request = null;

    public function __construct(
        string $message,
        ?Request $request = null,
        int $statusCode = Response::HTTP_BAD_REQUEST,
    ) {
        // DemosException expects ($userMsg, $logMsg, $code)
        // Pass message as both user message and log message
        parent::__construct($message, $message, $statusCode);
        $this->request = $request;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function getStatusCode(): int
    {
        return $this->getCode();
    }
}
