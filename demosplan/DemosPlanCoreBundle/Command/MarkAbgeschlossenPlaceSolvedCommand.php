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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Migration command to set solved=true on "Abgeschlossen" workflow places
 * for procedures that currently have no solved place.
 */
class MarkAbgeschlossenPlaceSolvedCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:workflow:mark-abgeschlossen-solved';
    protected static $defaultDescription = 'Set solved=true on "Abgeschlossen" workflow places';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ParameterBagInterface $parameterBag,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('procedure', 'p', InputOption::VALUE_REQUIRED, 'Only process this procedure ID')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would change without persisting')
            ->setHelp(
                <<<'EOT'
Sets solved=true on workflow places named "Abgeschlossen" for procedures
that currently have no place marked as solved.

Procedures with custom places that don't include "Abgeschlossen" are
skipped and listed separately so they can be reviewed manually.

Usage:
    php bin/console dplan:workflow:mark-abgeschlossen-solved
    php bin/console dplan:workflow:mark-abgeschlossen-solved --dry-run
    php bin/console dplan:workflow:mark-abgeschlossen-solved --procedure=<uuid>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $procedureId = $input->getOption('procedure');

        $io->title('Mark "Abgeschlossen" Workflow Places as Solved');

        if ($isDryRun) {
            $io->note('DRY-RUN mode — no changes will be persisted');
        }

        $conn = $this->entityManager->getConnection();

        // Find procedures without any solved place that DO have an "Abgeschlossen" place
        $sqlMatch = '
            SELECT wp.procedure_id, p._p_name, wp.id as place_id, wp.name as place_name
            FROM workflow_place wp
            INNER JOIN _procedure p ON wp.procedure_id = p._p_id
            WHERE wp.name = :placeName
              AND wp.procedure_id NOT IN (
                  SELECT DISTINCT procedure_id FROM workflow_place WHERE solved = 1
              )
        ';
        $params = ['placeName' => 'Abgeschlossen'];

        if (null !== $procedureId) {
            $sqlMatch .= ' AND wp.procedure_id = :procedureId';
            $params['procedureId'] = $procedureId;
        }

        $sqlMatch .= ' ORDER BY p._p_name';

        $matchingRows = $conn->fetchAllAssociative($sqlMatch, $params);

        // Find procedures without any solved place that do NOT have "Abgeschlossen"
        $sqlSkipped = "
            SELECT p._p_id, p._p_name,
                   GROUP_CONCAT(wp.name, ', ') as place_names
            FROM _procedure p
            INNER JOIN workflow_place wp ON wp.procedure_id = p._p_id
            WHERE p._p_id NOT IN (
                SELECT DISTINCT procedure_id FROM workflow_place WHERE solved = 1
            )
            AND p._p_id NOT IN (
                SELECT DISTINCT procedure_id FROM workflow_place WHERE name = :placeName
            )
        ";
        $skippedParams = ['placeName' => 'Abgeschlossen'];

        if (null !== $procedureId) {
            $sqlSkipped .= ' AND p._p_id = :procedureId';
            $skippedParams['procedureId'] = $procedureId;
        }

        $sqlSkipped .= ' GROUP BY p._p_id, p._p_name ORDER BY p._p_name';

        $skippedRows = $conn->fetchAllAssociative($sqlSkipped, $skippedParams);

        if ([] === $matchingRows && [] === $skippedRows) {
            $io->success('All procedures already have at least one solved place.');

            return Command::SUCCESS;
        }

        $this->updateMatchingProcedures($matchingRows, $isDryRun, $conn, $io);
        $this->reportSkippedProcedures($skippedRows, $io);

        return Command::SUCCESS;
    }

    private function updateMatchingProcedures(array $matchingRows, bool $isDryRun, $conn, SymfonyStyle $io): void
    {
        if ([] === $matchingRows) {
            return;
        }

        $io->info(sprintf('Found %d procedure(s) with "Abgeschlossen" place to mark as solved', count($matchingRows)));

        $updated = 0;
        foreach ($matchingRows as $row) {
            $io->text(sprintf('  [%s] %s', $row['procedure_id'], $row['_p_name']));

            if (!$isDryRun) {
                $conn->executeStatement(
                    'UPDATE workflow_place SET solved = 1 WHERE id = ?',
                    [$row['place_id']]
                );
            }

            ++$updated;
        }

        $io->success(sprintf('%s %d place(s)', $isDryRun ? 'Would update' : 'Updated', $updated));
    }

    private function reportSkippedProcedures(array $skippedRows, SymfonyStyle $io): void
    {
        if ([] === $skippedRows) {
            return;
        }

        $io->warning(sprintf(
            '%d procedure(s) have no "Abgeschlossen" place — review manually:',
            count($skippedRows)
        ));

        foreach ($skippedRows as $row) {
            $io->text(sprintf('  [%s] %s — places: %s', $row['_p_id'], $row['_p_name'], $row['place_names']));
        }
    }
}
