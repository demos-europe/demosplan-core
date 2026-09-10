<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Logic\File\FileConsistencyAuditor;
use demosplan\DemosPlanCoreBundle\Logic\File\FileConsistencyReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Runs the audit that {@link AuditFileConsistencyMessageHandler} performs nightly, on demand.
 *
 * Reads only; use `--samples` to list the findings the log entry samples.
 */
#[AsCommand(
    name: 'dplan:file:audit-consistency',
    description: 'Report files that exist in the database but not in the storage, and vice versa.'
)]
class FileConsistencyAuditCommand extends CoreCommand
{
    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly FileConsistencyAuditor $fileConsistencyAuditor,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addOption('samples', null, InputOption::VALUE_NONE, 'List the sampled findings, not only the counts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('File consistency audit');

        $report = $this->fileConsistencyAuditor->audit();

        $io->table(
            ['Check', 'Count'],
            [
                ['Database rows', $report->databaseRowCount],
                ['Soft deleted rows', $report->softDeletedRowCount],
                ['Storage objects', $report->storageObjectCount],
                ['Missing in storage', $report->missingInStorageCount],
                ['Orphaned in storage', $report->orphanedInStorageCount],
                ['Stored at unexpected path', $report->misplacedCount],
                ['Soft deleted but still stored', $report->softDeletedStillInStorageCount],
                ['Rows without hash', $report->rowsWithoutHashCount],
            ]
        );

        if ($input->getOption('samples')) {
            $this->writeSamples($io, $report);
        }

        if ($report->inventoryTruncated) {
            $io->warning('The storage listing hit its limit; the findings above are incomplete.');
        }

        if ($report->hasFindings()) {
            $io->warning(sprintf('Audit finished in %.1fs with findings.', $report->durationSeconds));

            return Command::FAILURE;
        }

        $io->success(sprintf('Audit finished in %.1fs, database and storage agree.', $report->durationSeconds));

        return Command::SUCCESS;
    }

    private function writeSamples(SymfonyStyle $io, FileConsistencyReport $report): void
    {
        $sampleSets = [
            'Missing in storage'            => $report->missingInStorageSamples,
            'Orphaned in storage'           => $report->orphanedInStorageSamples,
            'Stored at unexpected path'     => $report->misplacedSamples,
            'Soft deleted but still stored' => $report->softDeletedStillInStorageSamples,
            'Rows without hash'             => $report->rowsWithoutHashSamples,
        ];

        foreach ($sampleSets as $title => $samples) {
            if ([] === $samples) {
                continue;
            }
            $io->section($title);
            $io->table(array_keys($samples[0]), array_map('array_values', $samples));
        }
    }
}
