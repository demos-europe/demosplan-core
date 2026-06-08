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

use InvalidArgumentException;
use Predis\Client as PredisClient;
use SessionHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;

/**
 * Factory for creating session handlers based on environment configuration.
 *
 * Supports both Redis and Database session handlers by reading the SESSION_HANDLER
 * environment variable and creating the appropriate handler.
 */
class SessionHandlerFactory
{
    public function __construct(private readonly ParameterBagInterface $parameterBag)
    {
    }

    // called via framework.yaml
    public function createSessionHandler(string $sessionHandler): SessionHandlerInterface
    {
        if ('' === $sessionHandler || '0' === $sessionHandler) {
            throw new InvalidArgumentException('SESSION_HANDLER cannot be empty');
        }

        // Handle Redis connection strings
        if (str_starts_with($sessionHandler, 'redis://')) {
            return $this->createRedisHandler($sessionHandler);
        }

        // Handle database session handler class reference
        if (str_contains($sessionHandler, 'DatabaseSessionHandler')) {
            return $this->createDatabaseHandler();
        }

        throw new InvalidArgumentException(sprintf('Unsupported session handler configuration: %s', $sessionHandler));
    }

    private function createRedisHandler(string $dsn): RedisSessionHandler
    {
        // Use Predis for Redis connections
        $redis = new PredisClient($dsn);

        return new RedisSessionHandler($redis);
    }

    private function createDatabaseHandler(): DatabaseSessionHandler
    {
        return new DatabaseSessionHandler($this->parameterBag);
    }
}
