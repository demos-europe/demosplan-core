<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\File;

/**
 * Result of a {@link FileConsistencyAuditor} run.
 *
 * Counts are complete, sample lists are capped: a broken instance can produce hundreds of
 * thousands of findings, and a log entry has to stay readable and writable.
 */
final readonly class FileConsistencyReport
{
    /**
     * @param list<array{ident: string, hash: string, expectedPath: string}>    $missingInStorageSamples
     * @param list<array{path: string}>                                         $orphanedInStorageSamples
     * @param list<array{ident: string, expectedPath: string, foundAt: string}> $misplacedSamples
     * @param list<array{ident: string, path: string}>                          $softDeletedStillInStorageSamples
     * @param list<array{ident: string}>                                        $rowsWithoutHashSamples
     */
    public function __construct(
        public int $databaseRowCount,
        public int $softDeletedRowCount,
        public int $storageObjectCount,
        public int $missingInStorageCount,
        public array $missingInStorageSamples,
        public int $orphanedInStorageCount,
        public array $orphanedInStorageSamples,
        public int $misplacedCount,
        public array $misplacedSamples,
        public int $softDeletedStillInStorageCount,
        public array $softDeletedStillInStorageSamples,
        public int $rowsWithoutHashCount,
        public array $rowsWithoutHashSamples,
        public float $durationSeconds,
        public bool $inventoryTruncated,
    ) {
    }

    public function hasFindings(): bool
    {
        return 0 < $this->missingInStorageCount
            || 0 < $this->orphanedInStorageCount
            || 0 < $this->misplacedCount
            || 0 < $this->softDeletedStillInStorageCount
            || 0 < $this->rowsWithoutHashCount;
    }

    /**
     * Counts first, samples last.
     *
     * A log entry can be cut off by whatever writes or ships it, and the counts are what the entry
     * exists for; the samples are a starting point for digging and can afford to be the part that
     * is lost.
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'databaseRows'                     => $this->databaseRowCount,
            'softDeletedRows'                  => $this->softDeletedRowCount,
            'storageObjects'                   => $this->storageObjectCount,
            'missingInStorage'                 => $this->missingInStorageCount,
            'orphanedInStorage'                => $this->orphanedInStorageCount,
            'misplaced'                        => $this->misplacedCount,
            'softDeletedStillInStorage'        => $this->softDeletedStillInStorageCount,
            'rowsWithoutHash'                  => $this->rowsWithoutHashCount,
            'durationSeconds'                  => round($this->durationSeconds, 2),
            'inventoryTruncated'               => $this->inventoryTruncated,
            'missingInStorageSamples'          => $this->missingInStorageSamples,
            'orphanedInStorageSamples'         => $this->orphanedInStorageSamples,
            'misplacedSamples'                 => $this->misplacedSamples,
            'softDeletedStillInStorageSamples' => $this->softDeletedStillInStorageSamples,
            'rowsWithoutHashSamples'           => $this->rowsWithoutHashSamples,
        ];
    }
}
