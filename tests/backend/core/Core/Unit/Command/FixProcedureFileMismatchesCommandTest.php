<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\FixProcedureFileMismatchesCommand;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FixProcedureFileMismatchesCommandTest extends TestCase
{
    private const ELEMENT_DISCOVERY_SQL_FRAGMENT = 'FROM _elements ref';
    private const SINGLE_DOC_DISCOVERY_SQL_FRAGMENT = 'FROM _single_doc ref';

    private Connection&MockObject $connection;
    private EntityManagerInterface&MockObject $entityManager;
    private FileService&MockObject $fileService;
    private LoggerInterface&MockObject $logger;
    private ParameterBagInterface&MockObject $parameterBag;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->fileService = $this->createMock(FileService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
    }

    public function testDryRunReportsNothingToFixWhenDiscoveryEmpty(): void
    {
        $this->stubDiscovery(elementIds: [], singleDocIds: []);

        $tester = $this->makeTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Nothing to fix', $tester->getDisplay());
        self::assertStringNotContainsString('Applying fixes', $tester->getDisplay());
    }

    public function testDryRunReportsCountsAndDoesNotMutate(): void
    {
        $this->stubDiscovery(elementIds: ['e1'], singleDocIds: ['sd1', 'sd2']);
        $this->stubReportByOwningProcedure();

        // No find / flush / FileService calls expected during dry-run.
        $this->entityManager->expects(self::never())->method('find');
        $this->entityManager->expects(self::never())->method('flush');
        $this->fileService->expects(self::never())->method('createCopyOfFile');

        $tester = $this->makeTester();
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Dry-run only', $display);
        self::assertStringContainsString('_elements', $display);
        self::assertStringContainsString('_single_doc', $display);
    }

    public function testApplyFixesSingleDocMismatch(): void
    {
        $this->stubDiscovery(elementIds: [], singleDocIds: ['sd1']);
        $this->stubReportByOwningProcedure();

        $procedure = $this->makeProcedure('proc-derived');
        $singleDoc = $this->createMock(SingleDocument::class);
        $singleDoc->method('getDocument')->willReturn('old.pdf:old-ident:123:application/pdf');
        $singleDoc->method('getProcedure')->willReturn($procedure);
        $singleDoc->expects(self::once())
            ->method('setDocument')
            ->with('new.pdf:new-ident:123:application/pdf');

        $newFile = $this->createMock(File::class);
        $newFile->method('getFileString')->willReturn('new.pdf:new-ident:123:application/pdf');

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(SingleDocument::class, 'sd1')
            ->willReturn($singleDoc);

        $this->fileService
            ->expects(self::once())
            ->method('createCopyOfFile')
            ->with('old.pdf:old-ident:123:application/pdf', 'proc-derived')
            ->willReturn($newFile);

        // Flush is called once at the end of the loop (count < BATCH_SIZE).
        $this->entityManager->expects(self::atLeastOnce())->method('flush');

        $tester = $this->makeTester();
        $tester->execute(['--apply' => true], ['interactive' => false]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('Done', $display);
        self::assertStringNotContainsString('Aborted', $display);
    }

    public function testApplyFixesElementMismatch(): void
    {
        $this->stubDiscovery(elementIds: ['e1'], singleDocIds: []);
        $this->stubReportByOwningProcedure();

        $procedure = $this->makeProcedure('proc-derived');
        $element = $this->createMock(Elements::class);
        $element->method('getFile')->willReturn('plan.pdf:old-ident:456:application/pdf');
        $element->method('getProcedure')->willReturn($procedure);
        $element->expects(self::once())
            ->method('setFile')
            ->with('plan.pdf:new-ident:456:application/pdf');

        $newFile = $this->createMock(File::class);
        $newFile->method('getFileString')->willReturn('plan.pdf:new-ident:456:application/pdf');

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Elements::class, 'e1')
            ->willReturn($element);

        $this->fileService
            ->expects(self::once())
            ->method('createCopyOfFile')
            ->with('plan.pdf:old-ident:456:application/pdf', 'proc-derived')
            ->willReturn($newFile);

        $tester = $this->makeTester();
        $tester->execute(['--apply' => true], ['interactive' => false]);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testApplySkipsAndLogsWhenFileServiceReturnsNull(): void
    {
        $this->stubDiscovery(elementIds: [], singleDocIds: ['sd1']);
        $this->stubReportByOwningProcedure();

        $procedure = $this->makeProcedure('proc-derived');
        $singleDoc = $this->createMock(SingleDocument::class);
        $singleDoc->method('getDocument')->willReturn('old.pdf:old-ident:123:application/pdf');
        $singleDoc->method('getProcedure')->willReturn($procedure);
        $singleDoc->expects(self::never())->method('setDocument');

        $this->entityManager->method('find')->willReturn($singleDoc);
        $this->fileService->method('createCopyOfFile')->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('source file missing'),
                self::callback(static fn (array $ctx) => 'sd1' === ($ctx['_sd_id'] ?? null))
            );

        $tester = $this->makeTester();
        $tester->execute(['--apply' => true], ['interactive' => false]);

        // Skipped path returns SUCCESS (skip != error). Behaviour is asserted via
        // the logger expectation above and the absence of a setDocument call on
        // the SingleDocument mock.
        self::assertSame(0, $tester->getStatusCode());
        self::assertStringNotContainsString('Completed with errors', $tester->getDisplay());
    }

    public function testApplyReportsErrorWhenFileServiceThrows(): void
    {
        $this->stubDiscovery(elementIds: [], singleDocIds: ['sd1']);
        $this->stubReportByOwningProcedure();

        $procedure = $this->makeProcedure('proc-derived');
        $singleDoc = $this->createMock(SingleDocument::class);
        $singleDoc->method('getDocument')->willReturn('old.pdf:old-ident:123:application/pdf');
        $singleDoc->method('getProcedure')->willReturn($procedure);
        $singleDoc->expects(self::never())->method('setDocument');

        $this->entityManager->method('find')->willReturn($singleDoc);
        $this->fileService
            ->method('createCopyOfFile')
            ->willThrowException(new RuntimeException('blob storage unreachable'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('Failed to fix file reference'),
                self::callback(static fn (array $ctx) => 'sd1' === ($ctx['id'] ?? null)
                    && $ctx['exception'] instanceof RuntimeException)
            );

        $tester = $this->makeTester();
        $tester->execute(['--apply' => true], ['interactive' => false]);

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Completed with errors', $tester->getDisplay());
    }

    public function testProcedureOptionRestrictsBothQueries(): void
    {
        $this->connection
            ->expects(self::exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnCallback(function (string $sql, array $params): array {
                // Both discovery queries share the generic alias "ref".
                self::assertStringContainsString('ref._p_id = :pid', $sql);
                self::assertSame('only-this-procedure', $params['pid']);

                return [];
            });

        $this->stubReportByOwningProcedure();

        $tester = $this->makeTester();
        $tester->execute(['--procedure' => 'only-this-procedure']);

        self::assertSame(0, $tester->getStatusCode());
    }

    public function testIncludeDeletedOwnersDropsDeletedFilter(): void
    {
        $this->connection
            ->expects(self::exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnCallback(function (string $sql): array {
                self::assertStringNotContainsString('p._p_deleted = 0', $sql);

                return [];
            });

        $this->stubReportByOwningProcedure();

        $tester = $this->makeTester();
        $tester->execute(['--include-deleted-owners' => true]);

        self::assertSame(0, $tester->getStatusCode());
    }

    private function makeTester(): CommandTester
    {
        $command = new FixProcedureFileMismatchesCommand(
            $this->parameterBag,
            $this->connection,
            $this->entityManager,
            $this->fileService,
            $this->logger,
        );

        // CoreCommand::getApplication() insists on a ConsoleApplication being
        // attached, and CommandTester::execute() reaches for getApplication()
        // before running. Provide a stub so the check passes; the stub's empty
        // definition makes hasArgument('command') return false, skipping the
        // implicit "command" argument injection in CommandTester.
        $application = $this->createMock(ConsoleApplication::class);
        $application->method('getDefinition')->willReturn(new InputDefinition());
        $application->method('getHelperSet')->willReturn(new HelperSet());
        $command->setApplication($application);

        return new CommandTester($command);
    }

    /**
     * @param list<string> $elementIds
     * @param list<string> $singleDocIds
     */
    private function stubDiscovery(array $elementIds, array $singleDocIds): void
    {
        $this->connection
            ->method('fetchFirstColumn')
            ->willReturnCallback(static function (string $sql) use ($elementIds, $singleDocIds): array {
                if (str_contains($sql, self::ELEMENT_DISCOVERY_SQL_FRAGMENT)) {
                    return $elementIds;
                }
                if (str_contains($sql, self::SINGLE_DOC_DISCOVERY_SQL_FRAGMENT)) {
                    return $singleDocIds;
                }

                return [];
            });
    }

    /**
     * The "Top owning procedures" report runs a separate aggregate query.
     * Stubbing it to return [] keeps the test focused on the fix loop.
     */
    private function stubReportByOwningProcedure(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);
    }

    private function makeProcedure(string $id): Procedure&MockObject
    {
        $procedure = $this->createMock(Procedure::class);
        $procedure->method('getId')->willReturn($id);

        return $procedure;
    }
}
