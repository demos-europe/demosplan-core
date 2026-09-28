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

use demosplan\DemosPlanCoreBundle\Repository\FileRepository;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Psr\Log\LoggerInterface;

/**
 * Compares the `_files` table against the objects in the default storage.
 *
 * Files are referenced from other entities by filestring only, so a reference can outlive both the
 * row and the object. The audit answers the two questions that follow from that: which rows point
 * at an object that is not there, and which objects are not claimed by any row.
 *
 * A row is matched by its hash, not by its full path: the same physical object is shared between
 * rows when a file is copied into another procedure, and legacy rows store an absolute path from
 * the time before flysystem. A row whose hash is found somewhere else than where the row says is
 * therefore reported as misplaced rather than as missing.
 *
 * The audit only reads.
 */
class FileConsistencyAuditor
{
    /**
     * Findings are counted in full but only this many of each kind end up in the report, so that a
     * badly broken instance still produces a log entry that can be written and read.
     */
    private const SAMPLE_LIMIT = 50;

    /**
     * The storage inventory is held in memory to diff it against the database in a single pass.
     * Beyond this many objects the audit gives up instead of running the maintenance worker out of
     * memory, which would take the rest of the nightly tasks down with it.
     */
    private const MAX_TRACKED_OBJECTS = 3_000_000;

    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
        private readonly FileRepository $fileRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function audit(): FileConsistencyReport
    {
        $startedAt = microtime(true);

        [$objectPathsByHash, $storageObjectCount, $truncated] = $this->buildStorageInventory();

        $databaseRowCount = 0;
        $softDeletedRowCount = 0;
        /** @var array<string, true> $claimedHashes */
        $claimedHashes = [];
        $missingInStorage = new AuditFindings(self::SAMPLE_LIMIT);
        $misplaced = new AuditFindings(self::SAMPLE_LIMIT);
        $softDeletedStillInStorage = new AuditFindings(self::SAMPLE_LIMIT);
        $rowsWithoutHash = new AuditFindings(self::SAMPLE_LIMIT);

        foreach ($this->fileRepository->getFileLocationRows() as $row) {
            ++$databaseRowCount;
            $hash = $row['hash'];

            if (null === $hash || '' === $hash) {
                $rowsWithoutHash->add(['ident' => $row['ident']]);
                continue;
            }

            // Claim the hash even when the object is gone. Several rows may share one object, and
            // the orphan pass must not report an object that a row - misplaced or not - refers to.
            $claimedHashes[$hash] = true;
            $foundAt = $objectPathsByHash[$hash] ?? [];

            if ($row['deleted']) {
                ++$softDeletedRowCount;
                if ([] !== $foundAt) {
                    // Soft deleted rows are removed by CleanupFilesMessageHandler; an object that
                    // survives several audits points at a delete that keeps failing.
                    $softDeletedStillInStorage->add(['ident' => $row['ident'], 'path' => $foundAt[0]]);
                }
                continue;
            }

            $expectedPath = $this->getExpectedPath($row['path'], $hash);

            if ([] === $foundAt) {
                $missingInStorage->add([
                    'ident'        => $row['ident'],
                    'hash'         => $hash,
                    'expectedPath' => $expectedPath,
                ]);
                continue;
            }

            if (!$this->isStoredWhereExpected($expectedPath, $foundAt)) {
                $misplaced->add([
                    'ident'        => $row['ident'],
                    'expectedPath' => $expectedPath,
                    'foundAt'      => $foundAt[0],
                ]);
            }
        }

        $orphaned = $this->collectOrphanedObjects($objectPathsByHash, $claimedHashes);

        return new FileConsistencyReport(
            $databaseRowCount,
            $softDeletedRowCount,
            $storageObjectCount,
            $missingInStorage->getCount(),
            $missingInStorage->getSamples(),
            $orphaned->getCount(),
            $orphaned->getSamples(),
            $misplaced->getCount(),
            $misplaced->getSamples(),
            $softDeletedStillInStorage->getCount(),
            $softDeletedStillInStorage->getSamples(),
            $rowsWithoutHash->getCount(),
            $rowsWithoutHash->getSamples(),
            microtime(true) - $startedAt,
            $truncated,
        );
    }

    /**
     * Objects whose filename no row claims - unreachable from the application, still stored.
     *
     * @param array<string, list<string>> $objectPathsByHash
     * @param array<string, true>         $claimedHashes
     */
    private function collectOrphanedObjects(array $objectPathsByHash, array $claimedHashes): AuditFindings
    {
        $orphaned = new AuditFindings(self::SAMPLE_LIMIT);

        foreach ($objectPathsByHash as $hash => $paths) {
            if (isset($claimedHashes[$hash])) {
                continue;
            }

            foreach ($paths as $path) {
                $orphaned->add(['path' => $path]);
            }
        }

        return $orphaned;
    }

    /**
     * Indexes the storage by filename, which is the hash a `_files` row stores.
     *
     * @return array{0: array<string, list<string>>, 1: int, 2: bool}
     *
     * @throws FilesystemException
     */
    private function buildStorageInventory(): array
    {
        $objectPathsByHash = [];
        $objectCount = 0;
        $truncated = false;

        /** @var StorageAttributes $attributes */
        foreach ($this->defaultStorage->listContents('/', true) as $attributes) {
            if (!$attributes->isFile()) {
                continue;
            }

            if (self::MAX_TRACKED_OBJECTS <= $objectCount) {
                $this->logger->error(
                    'File consistency audit aborted the storage listing',
                    ['limit' => self::MAX_TRACKED_OBJECTS]
                );
                $truncated = true;
                break;
            }

            $path = $this->normalizePath($attributes->path());
            $objectPathsByHash[basename($path)][] = $path;
            ++$objectCount;
        }

        return [$objectPathsByHash, $objectCount, $truncated];
    }

    /**
     * The path a row claims its object lives under.
     */
    private function getExpectedPath(?string $path, string $hash): string
    {
        $path = $this->normalizePath($path ?? '');

        return '' === $path ? $hash : $path.'/'.$hash;
    }

    /**
     * Whether one of the places the object was found is the place the row means.
     *
     * Paths are compared by suffix, not for equality, because the two sides carry different roots.
     * Most rows predate flysystem and store an absolute path from the machine they were uploaded
     * on ('/srv/www/<instance>/files/2021/10'), while the storage knows that same object as
     * '2021/10/<hash>'; a bucket prefix produces the mirrored case. Matching the shared tail keeps
     * those rows out of the findings without having to know either root.
     *
     * @param list<string> $foundAt
     */
    private function isStoredWhereExpected(string $expectedPath, array $foundAt): bool
    {
        foreach ($foundAt as $actualPath) {
            if ($expectedPath === $actualPath
                || str_ends_with($expectedPath, '/'.$actualPath)
                || str_ends_with($actualPath, '/'.$expectedPath)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        // Legacy rows store paths relative to the instance directory, e.g. './files/2019/07'.
        $path = preg_replace('#^\./#', '', $path) ?? $path;

        return trim($path, '/');
    }
}
