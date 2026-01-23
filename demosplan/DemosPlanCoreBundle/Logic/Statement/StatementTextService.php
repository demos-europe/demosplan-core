<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Service for managing statement text computation and caching.
 *
 * For SEGMENTED statements, computing the text by concatenating all content blocks
 * can be expensive. This service provides caching to improve performance.
 */
class StatementTextService
{
    private const CACHE_KEY_PREFIX = 'statement_text_';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Compute the text for a statement.
     *
     * For UNSEGMENTED statements, returns the stored text.
     * For SEGMENTED statements, concatenates all content blocks in order.
     *
     * @param Statement $statement The statement to compute text for
     *
     * @return string The computed text
     */
    public function computeText(Statement $statement): string
    {
        // For unsegmented statements, return text directly
        if ($statement->getSegmentationStatus() === SegmentationStatus::UNSEGMENTED->value
            || null === $statement->getSegmentationStatus()) {
            return $statement->getText() ?? '';
        }

        // For segmented statements, check cache first
        $cached = $this->getCachedText($statement);
        if (null !== $cached) {
            return $cached;
        }

        // Compute from blocks
        $text = $this->computeTextFromBlocks($statement);

        // Cache the result
        $this->cacheText($statement, $text);

        return $text;
    }

    /**
     * Check if a statement's text needs recomputation.
     *
     * Returns true if:
     * - The statement is SEGMENTED and has no cached text
     * - The statement is SEGMENTED and cache has expired
     *
     * @param Statement $statement The statement to check
     *
     * @return bool True if recomputation is needed
     */
    public function needsRecomputation(Statement $statement): bool
    {
        if ($statement->getSegmentationStatus() !== SegmentationStatus::SEGMENTED->value) {
            return false; // UNSEGMENTED statements don't need computation
        }

        return null === $this->getCachedText($statement);
    }

    /**
     * Get cached text for a statement.
     *
     * Returns null if no cache entry exists or cache has expired.
     *
     * @param Statement $statement The statement to get cached text for
     *
     * @return string|null The cached text, or null if not cached
     */
    public function getCachedText(Statement $statement): ?string
    {
        $cacheKey = $this->getCacheKey($statement);
        $cacheItem = $this->cache->getItem($cacheKey);

        if (!$cacheItem->isHit()) {
            return null;
        }

        return $cacheItem->get();
    }

    /**
     * Invalidate the cached text for a statement.
     *
     * Should be called whenever the statement's content blocks change.
     *
     * @param Statement $statement The statement to invalidate cache for
     */
    public function invalidateTextCache(Statement $statement): void
    {
        $cacheKey = $this->getCacheKey($statement);
        $this->cache->deleteItem($cacheKey);
    }

    /**
     * Invalidate cached text for multiple statements at once.
     *
     * Useful for batch operations.
     *
     * @param Statement[] $statements The statements to invalidate cache for
     */
    public function invalidateTextCacheForMultiple(array $statements): void
    {
        $cacheKeys = [];
        foreach ($statements as $statement) {
            $cacheKeys[] = $this->getCacheKey($statement);
        }

        $this->cache->deleteItems($cacheKeys);
    }

    /**
     * Compute text from content blocks without caching.
     *
     * @param Statement $statement The statement to compute text for
     *
     * @return string The computed text
     */
    private function computeTextFromBlocks(Statement $statement): string
    {
        $blocks = $statement->getAllContentBlocks();

        return implode('', array_map(
            static fn ($block) => $block->getText(),
            $blocks
        ));
    }

    /**
     * Cache the computed text for a statement.
     *
     * @param Statement $statement The statement
     * @param string    $text      The computed text to cache
     */
    private function cacheText(Statement $statement, string $text): void
    {
        $cacheKey = $this->getCacheKey($statement);
        $cacheItem = $this->cache->getItem($cacheKey);

        $cacheItem->set($text);
        $cacheItem->expiresAfter(self::CACHE_TTL);

        $this->cache->save($cacheItem);
    }

    /**
     * Generate cache key for a statement.
     *
     * @param Statement $statement The statement
     *
     * @return string The cache key
     */
    private function getCacheKey(Statement $statement): string
    {
        return self::CACHE_KEY_PREFIX.$statement->getId();
    }

    /**
     * Precompute and cache text for multiple statements.
     *
     * Useful for warming up the cache before displaying a list of statements.
     *
     * @param Statement[] $statements The statements to precompute
     */
    public function precomputeTextForMultiple(array $statements): void
    {
        foreach ($statements as $statement) {
            if ($statement->getSegmentationStatus() === SegmentationStatus::SEGMENTED->value) {
                $this->computeText($statement);
            }
        }
    }
}
