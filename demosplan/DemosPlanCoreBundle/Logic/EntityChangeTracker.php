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

use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Enum\ChangeState;
use demosplan\DemosPlanCoreBundle\ValueObject\EntityChange;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Cache-based entity change tracker that monitors changes throughout the entity lifecycle.
 */
class EntityChangeTracker
{
    private const CACHE_KEY_PREFIX = 'entity_change_tracker';
    private const CACHE_TTL = 3600; // 1 hour

    private bool $trackingEnabled = false;

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Start tracking changes for entities.
     */
    public function startTracking(): void
    {
        $this->trackingEnabled = true;
    }

    /**
     * Stop tracking changes for entities.
     */
    public function stopTracking(): void
    {
        $this->trackingEnabled = false;
    }

    /**
     * Check if tracking is currently enabled.
     */
    public function isTrackingEnabled(): bool
    {
        return $this->trackingEnabled;
    }

    /**
     * Track a change made to an entity field.
     */
    public function trackChange(
        string $entityClass,
        string $entityId,
        string $fieldName,
        mixed $oldValue,
        mixed $newValue,
        bool $isRelationChange = false
    ): void {
        if (!$this->trackingEnabled) {
            return;
        }

        $change = new EntityChange(
            entityClass: $entityClass,
            entityId: $entityId,
            fieldName: $fieldName,
            oldValue: $oldValue,
            newValue: $newValue,
            state: ChangeState::IN_MEMORY,
            timestamp: new DateTimeImmutable(),
            isRelationChange: $isRelationChange
        );

        $this->storeChange($change);
    }

    /**
     * Update the state of tracked changes to FLUSHED for a specific entity.
     */
    public function markChangesFlushed(string $entityId): void
    {
        if (!$this->trackingEnabled) {
            return;
        }

        $this->updateChangesState($entityId, ChangeState::IN_MEMORY, ChangeState::FLUSHED);
    }

    /**
     * Update the state of tracked changes to COMMITTED for a specific entity.
     */
    public function markChangesCommitted(string $entityId): void
    {
        if (!$this->trackingEnabled) {
            return;
        }

        $this->updateChangesState($entityId, ChangeState::FLUSHED, ChangeState::COMMITTED);
    }

    /**
     * Update the state of tracked changes to ROLLED_BACK for a specific entity.
     */
    public function markChangesRolledBack(string $entityId): void
    {
        if (!$this->trackingEnabled) {
            return;
        }

        $changes = $this->getTrackedChangesForEntity($entityId);
        $updatedChanges = [];

        foreach ($changes as $change) {
            if (!$change->getState()->isPersisted()) {
                $updatedChanges[] = $change->withState(ChangeState::ROLLED_BACK);
            } else {
                $updatedChanges[] = $change;
            }
        }

        $this->storeChangesForEntity($entityId, $updatedChanges);
    }

    /**
     * Get tracked changes for a specific entity.
     *
     * @return EntityChange[]
     */
    public function getTrackedChangesForEntity(string $entityId): array
    {
        if (!$this->trackingEnabled) {
            return [];
        }

        $cacheKey = $this->getCacheKey($entityId);
        
        return $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_TTL);
            return [];
        });
    }

    /**
     * Get tracked changes for a specific entity and field.
     *
     * @return EntityChange[]
     */
    public function getTrackedChangesForField(string $entityId, string $fieldName): array
    {
        $allChanges = $this->getTrackedChangesForEntity($entityId);

        return array_filter($allChanges, fn (EntityChange $change) => 
            $change->getFieldName() === $fieldName
        );
    }

    /**
     * Clear tracked changes for a specific entity.
     */
    public function clearTrackedChangesForEntity(string $entityId): void
    {
        $cacheKey = $this->getCacheKey($entityId);
        $this->cache->delete($cacheKey);
    }

    /**
     * Update changes state for a specific entity.
     */
    private function updateChangesState(string $entityId, ChangeState $fromState, ChangeState $toState): void
    {
        $changes = $this->getTrackedChangesForEntity($entityId);
        $updatedChanges = [];

        foreach ($changes as $change) {
            if ($change->getState() === $fromState) {
                $updatedChanges[] = $change->withState($toState);
            } else {
                $updatedChanges[] = $change;
            }
        }

        $this->storeChangesForEntity($entityId, $updatedChanges);
    }

    /**
     * Store a single change in the cache.
     */
    private function storeChange(EntityChange $change): void
    {
        $changes = $this->getTrackedChangesForEntity($change->getEntityId());
        $changeKey = $change->getChangeKey();

        // Update existing change or add new one
        $found = false;
        foreach ($changes as $index => $existing) {
            if ($existing->getChangeKey() === $changeKey) {
                $changes[$index] = $change;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $changes[] = $change;
        }

        $this->storeChangesForEntity($change->getEntityId(), $changes);
    }

    /**
     * Store changes for a specific entity in the cache.
     *
     * @param EntityChange[] $changes
     */
    private function storeChangesForEntity(string $entityId, array $changes): void
    {
        $cacheKey = $this->getCacheKey($entityId);
        $this->cache->delete($cacheKey);
        
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($changes) {
            $item->expiresAfter(self::CACHE_TTL);
            return $changes;
        });
    }

    /**
     * Get the cache key for a specific entity.
     */
    private function getCacheKey(string $entityId): string
    {
        return sprintf('%s_%s', self::CACHE_KEY_PREFIX, $entityId);
    }
}