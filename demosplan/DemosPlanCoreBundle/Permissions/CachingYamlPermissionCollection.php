<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Permissions;

use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function array_key_exists;

class CachingYamlPermissionCollection implements PermissionCollectionInterface
{
    /**
     * @param non-empty-string $path
     * @param non-empty-string $cacheKey
     */
    public function __construct(private readonly CacheInterface $cache, private readonly LoggerInterface $logger, private readonly string $path, private readonly string $cacheKey, private readonly GlobalConfig $globalConfig)
    {
    }

    public function toArray(): array
    {
        return $this->cache->get($this->cacheKey, function (ItemInterface $item): array {
            $this->logger->info("Read Permissions from YAML: $this->path");
            $permissions = collect(Yaml::parseFile(DemosPlanPath::getConfigPath($this->path)))
                ->map(
                    static fn ($permissionsArray, $permissionName) => Permission::instanceFromArray($permissionName, $permissionsArray)
                )->toArray();

            $ttl = $this->getTtl();
            $this->logger->info("Save Permissions into cache with ttl $ttl");
            $item->expiresAfter($ttl);

            return $permissions;
        });
    }

    public function getPermission(string $permissionKey): Permission
    {
        $permissions = $this->toArray();

        if (!array_key_exists($permissionKey, $permissions)) {
            throw new InvalidArgumentException("No permission with key `$permissionKey` exists in this collection.");
        }

        return $permissions[$permissionKey];
    }

    public function containsPermission(string $permissionKey): bool
    {
        return array_key_exists($permissionKey, $this->toArray());
    }

    /**
     * @return int<1, max>
     */
    protected function getTtl(): int
    {
        // set long ttl only in prod mode to improve DX in dev mode when working with permissions
        return $this->globalConfig->isProdMode() ? 3600 : 10;
    }
}
