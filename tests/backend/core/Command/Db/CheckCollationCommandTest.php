<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Command\Db;

use demosplan\DemosPlanCoreBundle\Command\Db\CheckCollationCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CheckCollationCommandTest extends TestCase
{
    private (MockObject&Connection)|null $connectionMock = null;

    /** @var list<array<string, string>> */
    private array $tableDeviations = [];

    /** @var list<array<string, string>> */
    private array $fkMismatches = [];

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->connectionMock->method('getDatabase')->willReturn('test_db');
    }

    public function testConsistentDatabaseReturnsSuccess(): void
    {
        $this->mockQueries();

        $tester = $this->executeCommand();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('consistent', $tester->getDisplay());
    }

    public function testTableDeviationsReturnsFailure(): void
    {
        $this->tableDeviations = [
            ['TABLE_NAME' => 'customer_oauth_config', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'],
        ];
        $this->mockQueries();

        $tester = $this->executeCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('customer_oauth_config', $output);
        self::assertStringContainsString('utf8mb4_unicode_ci', $output);
        self::assertStringContainsString('1 table(s) deviate', $output);
    }

    public function testFkMismatchesReturnsFailure(): void
    {
        $this->fkMismatches = [
            [
                'child_table'    => 'customer_oauth_config',
                'child_column'   => 'customer_id',
                'child_charset'  => 'utf8mb4',
                'parent_table'   => 'customer',
                'parent_column'  => '_c_id',
                'parent_charset' => 'utf8mb3',
            ],
        ];
        $this->mockQueries();

        $tester = $this->executeCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('1 FK column pair(s)', $output);
        self::assertStringContainsString('customer_oauth_config', $output);
    }

    public function testFixExecutesAlterStatements(): void
    {
        $this->tableDeviations = [
            ['TABLE_NAME' => 'customer_oauth_config', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'],
            ['TABLE_NAME' => 'import_job', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'],
        ];
        $this->mockQueries();

        $this->connectionMock->expects(self::exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql): int {
                self::assertStringContainsString('CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci', $sql);

                return 0;
            });

        $tester = $this->executeCommand(['--fix' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Fixed', $output);
        self::assertStringContainsString('Converted 2 table(s)', $output);
    }

    public function testDryRunPrintsSqlWithoutExecuting(): void
    {
        $this->tableDeviations = [
            ['TABLE_NAME' => 'customer_oauth_config', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'],
        ];
        $this->mockQueries();

        $this->connectionMock->expects(self::never())->method('executeStatement');

        $tester = $this->executeCommand(['--fix' => true, '--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('ALTER TABLE `customer_oauth_config` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci;', $output);
        self::assertStringContainsString('dry-run', $output);
    }

    public function testFixReportsFailedAlters(): void
    {
        $this->tableDeviations = [
            ['TABLE_NAME' => 'problematic_table', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'],
        ];
        $this->mockQueries();

        $this->connectionMock->method('executeStatement')
            ->willThrowException(new \Exception('Cannot convert table'));

        $tester = $this->executeCommand(['--fix' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('Failed', $output);
        self::assertStringContainsString('Cannot convert table', $output);
    }

    public function testMultipleDeviationsAllListed(): void
    {
        $this->tableDeviations = [
            ['TABLE_NAME' => 'table_a', 'TABLE_COLLATION' => 'utf8mb4_unicode_ci'],
            ['TABLE_NAME' => 'table_b', 'TABLE_COLLATION' => 'utf8mb4_general_ci'],
            ['TABLE_NAME' => 'table_c', 'TABLE_COLLATION' => 'latin1_swedish_ci'],
        ];
        $this->mockQueries();

        $tester = $this->executeCommand();

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        $output = $tester->getDisplay();
        self::assertStringContainsString('3 table(s) deviate', $output);
        self::assertStringContainsString('table_a', $output);
        self::assertStringContainsString('table_b', $output);
        self::assertStringContainsString('table_c', $output);
    }

    public function testServerSettingsAreDisplayed(): void
    {
        $this->mockQueries([
            ['Variable_name' => 'character_set_server', 'Value' => 'utf8mb4'],
            ['Variable_name' => 'collation_server', 'Value' => 'utf8mb4_unicode_ci'],
            ['Variable_name' => 'old_mode', 'Value' => ''],
        ]);

        $tester = $this->executeCommand();

        $output = $tester->getDisplay();
        self::assertStringContainsString('character_set_server', $output);
        self::assertStringContainsString('utf8mb4', $output);
        self::assertStringContainsString('old_mode', $output);
    }

    public function testExpectedCollationShownInOutput(): void
    {
        $this->mockQueries();

        $tester = $this->executeCommand();

        $output = $tester->getDisplay();
        self::assertStringContainsString('utf8mb3', $output);
        self::assertStringContainsString('utf8mb3_unicode_ci', $output);
    }

    public function testCustomDefaultTableOptionsAreUsed(): void
    {
        $this->mockQueries();

        $command = new CheckCollationCommand(
            $this->connectionMock,
            ['charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci'],
        );
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('utf8mb4 / utf8mb4_unicode_ci', $output);
    }

    private function mockQueries(?array $serverVariables = null): void
    {
        $serverVariables ??= [
            ['Variable_name' => 'character_set_server', 'Value' => 'utf8mb4'],
            ['Variable_name' => 'collation_server', 'Value' => 'utf8mb4_unicode_ci'],
            ['Variable_name' => 'old_mode', 'Value' => ''],
        ];

        $tableDeviations = $this->tableDeviations;
        $fkMismatches = $this->fkMismatches;

        $this->connectionMock->method('fetchAllAssociative')
            ->willReturnCallback(static function (string $sql) use ($serverVariables, $tableDeviations, $fkMismatches): array {
                if (str_contains($sql, 'SHOW VARIABLES')) {
                    return $serverVariables;
                }
                if (str_contains($sql, 'information_schema.TABLES')) {
                    return $tableDeviations;
                }
                if (str_contains($sql, 'KEY_COLUMN_USAGE')) {
                    return $fkMismatches;
                }

                return [];
            });
    }

    /**
     * @param array<string, mixed> $options
     */
    private function executeCommand(array $options = []): CommandTester
    {
        $command = new CheckCollationCommand($this->connectionMock);
        $tester = new CommandTester($command);
        $tester->execute($options);

        return $tester;
    }
}
