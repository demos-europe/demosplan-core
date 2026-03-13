<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Db;

use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dplan:db:check-collation',
    description: 'Audit database tables for charset/collation consistency'
)]
class CheckCollationCommand extends Command
{
    private string $expectedCollation;
    private string $expectedCharset;

    /**
     * @param array{charset?: string, collate?: string} $defaultTableOptions
     */
    public function __construct(
        private readonly Connection $connection,
        array $defaultTableOptions = [],
    ) {
        $this->expectedCharset = $defaultTableOptions['charset'] ?? 'utf8mb3';
        $this->expectedCollation = $defaultTableOptions['collate'] ?? 'utf8mb3_unicode_ci';

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'fix',
            null,
            InputOption::VALUE_NONE,
            'Convert deviant tables to the expected collation'
        );

        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'With --fix: print SQL without executing'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fix = $input->getOption('fix');
        $dryRun = $input->getOption('dry-run');
        $dbName = $this->connection->getDatabase();
        $hasMismatches = false;

        $io->title('Database Collation Audit');
        $io->comment(sprintf('Expected: %s / %s', $this->expectedCharset, $this->expectedCollation));

        // 1. Server settings
        $this->reportServerSettings($io);

        // 2. Table collation check
        $deviations = $this->findTableCollationDeviations($dbName);
        if ([] !== $deviations) {
            $hasMismatches = true;
            $io->section('Table Collation Deviations');
            $io->warning(sprintf('%d table(s) deviate from %s', count($deviations), $this->expectedCollation));

            $table = new Table($output);
            $table->setHeaders(['Table', 'Current Collation']);
            foreach ($deviations as $row) {
                $table->addRow([$row['TABLE_NAME'], $row['TABLE_COLLATION']]);
            }
            $table->render();

            if ($fix) {
                $this->fixTableCollations($io, $deviations, $dryRun);
            }
        } else {
            $io->success('All tables use '.$this->expectedCollation);
        }

        // 3. FK column charset mismatches
        $fkMismatches = $this->findForeignKeyCharsetMismatches($dbName);
        if ([] !== $fkMismatches) {
            $hasMismatches = true;
            $io->section('Foreign Key Charset Mismatches');
            $io->warning(sprintf('%d FK column pair(s) have charset mismatches', count($fkMismatches)));

            $table = new Table($output);
            $table->setHeaders([
                'Child Table',
                'Child Column',
                'Child Charset',
                'Parent Table',
                'Parent Column',
                'Parent Charset',
            ]);
            foreach ($fkMismatches as $row) {
                $table->addRow([
                    $row['child_table'],
                    $row['child_column'],
                    $row['child_charset'],
                    $row['parent_table'],
                    $row['parent_column'],
                    $row['parent_charset'],
                ]);
            }
            $table->render();
        } else {
            $io->success('No foreign key charset mismatches found');
        }

        if ($hasMismatches && !$fix) {
            $io->error('Collation inconsistencies detected. Use --fix to convert.');

            return Command::FAILURE;
        }

        if (!$hasMismatches) {
            $io->success('Database charset/collation is consistent');
        }

        return $hasMismatches ? Command::FAILURE : Command::SUCCESS;
    }

    private function fixTableCollations(SymfonyStyle $io, array $deviations, bool $dryRun): void
    {
        $io->newLine();
        $io->section($dryRun ? 'SQL to fix table collations (dry-run)' : 'Fixing table collations');

        $fixed = 0;
        $failed = 0;

        foreach ($deviations as $row) {
            $tableName = $row['TABLE_NAME'];
            $sql = sprintf(
                'ALTER TABLE `%s` CONVERT TO CHARACTER SET %s COLLATE %s',
                $tableName,
                $this->expectedCharset,
                $this->expectedCollation
            );

            if ($dryRun) {
                $io->writeln($sql.';');
                continue;
            }

            try {
                $this->connection->executeStatement($sql);
                $io->writeln(sprintf('  <info>Fixed:</info> %s', $tableName));
                ++$fixed;
            } catch (Exception $e) {
                $io->writeln(sprintf('  <error>Failed:</error> %s — %s', $tableName, $e->getMessage()));
                ++$failed;
            }
        }

        if ($dryRun) {
            return;
        }

        $io->newLine();
        if (0 === $failed) {
            $io->success(sprintf('Converted %d table(s) to %s', $fixed, $this->expectedCollation));
        } else {
            $io->warning(sprintf('Converted %d table(s), %d failed', $fixed, $failed));
        }
    }

    private function reportServerSettings(SymfonyStyle $io): void
    {
        $io->section('Server Settings');

        $variables = $this->connection->fetchAllAssociative(
            "SHOW VARIABLES WHERE Variable_name IN ('character_set_server', 'collation_server', 'old_mode')"
        );

        $rows = [];
        foreach ($variables as $variable) {
            $rows[] = [$variable['Variable_name'], $variable['Value']];
        }

        $io->table(['Variable', 'Value'], $rows);
    }

    private function findTableCollationDeviations(string $dbName): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT TABLE_NAME, TABLE_COLLATION
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :db
               AND TABLE_TYPE = :type
               AND TABLE_COLLATION != :expected
             ORDER BY TABLE_NAME',
            [
                'db'       => $dbName,
                'type'     => 'BASE TABLE',
                'expected' => $this->expectedCollation,
            ]
        );
    }

    private function findForeignKeyCharsetMismatches(string $dbName): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                kcu.TABLE_NAME AS child_table,
                kcu.COLUMN_NAME AS child_column,
                c1.CHARACTER_SET_NAME AS child_charset,
                kcu.REFERENCED_TABLE_NAME AS parent_table,
                kcu.REFERENCED_COLUMN_NAME AS parent_column,
                c2.CHARACTER_SET_NAME AS parent_charset
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.COLUMNS c1
                ON c1.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                AND c1.TABLE_NAME = kcu.TABLE_NAME
                AND c1.COLUMN_NAME = kcu.COLUMN_NAME
            JOIN information_schema.COLUMNS c2
                ON c2.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                AND c2.TABLE_NAME = kcu.REFERENCED_TABLE_NAME
                AND c2.COLUMN_NAME = kcu.REFERENCED_COLUMN_NAME
            WHERE kcu.TABLE_SCHEMA = :db
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                AND c1.CHARACTER_SET_NAME != c2.CHARACTER_SET_NAME
            ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME',
            ['db' => $dbName]
        );
    }
}
