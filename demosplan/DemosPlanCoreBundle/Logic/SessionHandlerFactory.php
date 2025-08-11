<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic;

use SessionHandlerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
use Predis\Client as PredisClient;
use InvalidArgumentException;

/**
 * Factory for creating session handlers based on environment configuration.
 *
 * Supports both Redis and Database session handlers by reading the SESSION_HANDLER
 * environment variable and creating the appropriate handler.
 */
class SessionHandlerFactory
{
    private ParameterBagInterface $parameterBag;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    // called via framework.yaml
    public function createSessionHandler(string $sessionHandler): SessionHandlerInterface
    {
        if (empty($sessionHandler)) {
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

        throw new InvalidArgumentException(
            sprintf('Unsupported session handler configuration: %s', $sessionHandler)
        );
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
