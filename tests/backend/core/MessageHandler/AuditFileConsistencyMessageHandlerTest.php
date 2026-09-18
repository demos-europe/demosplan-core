<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\MessageHandler;

use demosplan\DemosPlanCoreBundle\Logic\File\FileConsistencyAuditor;
use demosplan\DemosPlanCoreBundle\Logic\File\FileConsistencyReport;
use demosplan\DemosPlanCoreBundle\Message\AuditFileConsistencyMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\AuditFileConsistencyMessageHandler;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AuditFileConsistencyMessageHandlerTest extends TestCase
{
    private ?FileConsistencyAuditor $auditor = null;
    private ?LoggerInterface $logger = null;
    private ?AuditFileConsistencyMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditor = $this->createMock(FileConsistencyAuditor::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new AuditFileConsistencyMessageHandler($this->auditor, $this->logger);
    }

    public function testLogsInfoEntryWhenStorageAndDatabaseAgree(): void
    {
        $this->auditor->method('audit')->willReturn($this->createReport(0));

        $this->logger->expects(self::once())->method('info')
            ->with('File consistency audit found no inconsistencies', self::isType('array'));
        $this->logger->expects(self::never())->method('warning');

        ($this->sut)(new AuditFileConsistencyMessage());
    }

    public function testLogsWarningEntryWithCountsWhenFilesAreMissing(): void
    {
        $this->auditor->method('audit')->willReturn($this->createReport(3));

        $this->logger->expects(self::once())->method('warning')
            ->with(
                'File consistency audit found inconsistencies',
                self::callback(static fn (array $context): bool => 3 === $context['missingInStorage'])
            );

        ($this->sut)(new AuditFileConsistencyMessage());
    }

    /**
     * A log entry can be cut off by whatever writes or ships it, so every count has to appear
     * before the first sample list.
     */
    public function testLogContextListsAllCountsBeforeAnySamples(): void
    {
        $this->auditor->method('audit')->willReturn($this->createReport(3));

        $this->logger->expects(self::once())->method('warning')
            ->with(self::anything(), self::callback(static function (array $context): bool {
                $keys = array_keys($context);
                $firstSample = min(array_map(
                    static fn (string $key): int => array_search($key, $keys, true),
                    array_filter($keys, static fn (string $key): bool => str_ends_with($key, 'Samples'))
                ));
                $counts = array_filter($keys, static fn (string $key): bool => !str_ends_with($key, 'Samples'));

                return max(array_map(
                    static fn (string $key): int => array_search($key, $keys, true),
                    $counts
                )) < $firstSample;
            }));

        ($this->sut)(new AuditFileConsistencyMessage());
    }

    /**
     * A failing audit must not take the remaining nightly maintenance tasks down with it.
     */
    public function testLogsErrorWhenAuditFails(): void
    {
        $this->auditor->method('audit')->willThrowException(new Exception('storage unreachable'));

        $this->logger->expects(self::once())->method('error');

        ($this->sut)(new AuditFileConsistencyMessage());
    }

    private function createReport(int $missingInStorageCount): FileConsistencyReport
    {
        return new FileConsistencyReport(
            10,
            0,
            10,
            $missingInStorageCount,
            [],
            0,
            [],
            0,
            [],
            0,
            [],
            0,
            [],
            1.5,
            false
        );
    }
}
