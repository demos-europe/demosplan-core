<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\File\Unit;

use demosplan\DemosPlanCoreBundle\Logic\File\FileConsistencyAuditor;
use demosplan\DemosPlanCoreBundle\Repository\FileRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Drives {@link FileConsistencyAuditor} against an in-memory storage and a stubbed row stream, so
 * the classification of each finding is asserted without touching S3 or the database.
 */
class FileConsistencyAuditorTest extends TestCase
{
    private ?FilesystemOperator $storage = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function testReportsNoFindingsWhenStorageMatchesDatabase(): void
    {
        $this->storage->write('2026/07/hash-a', 'content');
        $this->storage->write('2026/08/hash-b', 'content');

        $report = $this->createAuditor([
            $this->row('ident-a', 'hash-a', '2026/07'),
            $this->row('ident-b', 'hash-b', '2026/08'),
        ])->audit();

        self::assertFalse($report->hasFindings());
        self::assertSame(2, $report->databaseRowCount);
        self::assertSame(2, $report->storageObjectCount);
    }

    public function testReportsRowWithoutStorageObject(): void
    {
        $report = $this->createAuditor([$this->row('ident-a', 'hash-a', '2026/07')])->audit();

        self::assertSame(1, $report->missingInStorageCount);
        self::assertSame(
            [['ident' => 'ident-a', 'hash' => 'hash-a', 'expectedPath' => '2026/07/hash-a']],
            $report->missingInStorageSamples
        );
    }

    public function testReportsStorageObjectWithoutRow(): void
    {
        $this->storage->write('2026/07/hash-a', 'content');
        $this->storage->write('2026/07/orphan', 'content');

        $report = $this->createAuditor([$this->row('ident-a', 'hash-a', '2026/07')])->audit();

        self::assertSame(0, $report->missingInStorageCount);
        self::assertSame(1, $report->orphanedInStorageCount);
        self::assertSame([['path' => '2026/07/orphan']], $report->orphanedInStorageSamples);
    }

    public function testReportsObjectStoredAtUnexpectedPath(): void
    {
        $this->storage->write('2019/01/hash-a', 'content');

        $report = $this->createAuditor([$this->row('ident-a', 'hash-a', '2026/07')])->audit();

        self::assertSame(0, $report->missingInStorageCount);
        self::assertSame(0, $report->orphanedInStorageCount);
        self::assertSame(
            [['ident' => 'ident-a', 'expectedPath' => '2026/07/hash-a', 'foundAt' => '2019/01/hash-a']],
            $report->misplacedSamples
        );
    }

    /**
     * Rows written before flysystem store an absolute path from the machine the upload happened on.
     * The storage root is not part of that path, so the shared tail decides.
     */
    public function testAcceptsLegacyAbsolutePathOfAnyRoot(): void
    {
        $this->storage->write('2019/01/hash-a', 'content');

        $report = $this->createAuditor([
            $this->row('ident-a', 'hash-a', '/srv/www/demos/some.instance.de/files/2019/01'),
        ])->audit();

        self::assertFalse($report->hasFindings());
    }

    /**
     * The mirrored case: the storage prepends a bucket prefix the row never knew about.
     */
    public function testAcceptsStoragePrefixMissingFromTheRow(): void
    {
        $this->storage->write('bucket-prefix/2019/01/hash-a', 'content');

        $report = $this->createAuditor([$this->row('ident-a', 'hash-a', '2019/01')])->audit();

        self::assertFalse($report->hasFindings());
    }

    public function testAcceptsObjectInStorageRootForRowWithoutPath(): void
    {
        $this->storage->write('hash-a', 'content');

        $report = $this->createAuditor([$this->row('ident-a', 'hash-a', '')])->audit();

        self::assertFalse($report->hasFindings());
    }

    /**
     * Copying a file into another procedure creates a second row for the same object.
     */
    public function testSharedHashIsClaimedByEveryRow(): void
    {
        $this->storage->write('2026/07/hash-a', 'content');

        $report = $this->createAuditor([
            $this->row('ident-a', 'hash-a', '2026/07'),
            $this->row('ident-b', 'hash-a', '2026/07'),
        ])->audit();

        self::assertFalse($report->hasFindings());
        self::assertSame(2, $report->databaseRowCount);
    }

    public function testSoftDeletedRowKeepsItsObjectOutOfTheOrphanCount(): void
    {
        $this->storage->write('2026/07/hash-a', 'content');

        $report = $this->createAuditor([$this->row('ident-a', 'hash-a', '2026/07', true)])->audit();

        self::assertSame(0, $report->orphanedInStorageCount);
        self::assertSame(0, $report->missingInStorageCount);
        self::assertSame(1, $report->softDeletedRowCount);
        self::assertSame(1, $report->softDeletedStillInStorageCount);
    }

    public function testSoftDeletedRowWithoutObjectIsNotAFinding(): void
    {
        $report = $this->createAuditor([$this->row('ident-a', 'hash-a', '2026/07', true)])->audit();

        self::assertFalse($report->hasFindings());
    }

    public function testReportsRowWithoutHash(): void
    {
        $report = $this->createAuditor([$this->row('ident-a', null, '2026/07')])->audit();

        self::assertSame(1, $report->rowsWithoutHashCount);
        self::assertSame([['ident' => 'ident-a']], $report->rowsWithoutHashSamples);
    }

    /**
     * @return array{ident: string, hash: string|null, path: string|null, deleted: bool}
     */
    private function row(string $ident, ?string $hash, ?string $path, bool $deleted = false): array
    {
        return ['ident' => $ident, 'hash' => $hash, 'path' => $path, 'deleted' => $deleted];
    }

    /**
     * @param list<array{ident: string, hash: string|null, path: string|null, deleted: bool}> $rows
     */
    private function createAuditor(array $rows): FileConsistencyAuditor
    {
        $fileRepository = $this->createMock(FileRepository::class);
        $fileRepository->method('getFileLocationRows')->willReturn($rows);

        return new FileConsistencyAuditor($this->storage, $fileRepository, new NullLogger());
    }
}
