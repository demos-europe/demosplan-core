<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ValidationLogger implements ValidationLoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public function logValidationFailure(Request $request, InvalidDataException $exception): void
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        $userId = $user && !is_string($user) ? $user->getUserIdentifier() : 'anonymous';

        $logContext = [
            'path' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
            'userId' => $userId,
            'errorCode' => $exception->getStatusCode(),
            'errorMessage' => $exception->getMessage(),
        ];

        // Log the validation failure
        $this->logger->warning('Input validation failed', $logContext);
    }
}
