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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Migration command to sync statement processing status (_st_status)
 * based on the solved state of each statement's segments' workflow places.
 */
class SyncStatementStatusCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:statement:sync-status';
    protected static $defaultDescription = 'Sync statement processing status from segment workflow places';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatementService $statementService,
        ParameterBagInterface $parameterBag,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this
            ->addOption('procedure', 'p', InputOption::VALUE_REQUIRED, 'Only process statements in this procedure ID')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Show what would change without persisting')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Flush every N statements', '100')
            ->setHelp(
                <<<'EOT'
Computes the correct processing status for each statement that has segments
and updates _st_status if it differs from the current value.

Status logic:
  - No segments        → 'new'
  - All places solved  → 'completed'
  - Otherwise          → 'processing'

Usage:
    php bin/console dplan:statement:sync-status
    php bin/console dplan:statement:sync-status --dry-run
    php bin/console dplan:statement:sync-status --procedure=<uuid>
    php bin/console dplan:statement:sync-status --batch-size=50
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $procedureId = $input->getOption('procedure');
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('Sync Statement Processing Status');

        if ($isDryRun) {
            $io->note('DRY-RUN mode — no changes will be persisted');
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(Statement::class, 's')
            ->where('s.id IN (SELECT IDENTITY(seg.parentStatementOfSegment) FROM '.Segment::class.' seg WHERE seg.parentStatementOfSegment IS NOT NULL)');

        if (null !== $procedureId) {
            $qb->andWhere('s.procedure = :procedureId')
                ->setParameter('procedureId', $procedureId);
        }

        $statements = $qb->getQuery()->getResult();

        $total = count($statements);
        $io->info("Found {$total} statement(s) with segments");

        $updated = 0;
        $skipped = 0;

        foreach ($statements as $i => $statement) {
            $computedStatus = $this->statementService->getProcessingStatus($statement);
            $currentStatus = $statement->getStatus();

            if ($currentStatus === $computedStatus) {
                ++$skipped;
                continue;
            }

            if ($isDryRun) {
                $io->text(sprintf(
                    '  [%s] %s → %s',
                    $statement->getId(),
                    $currentStatus,
                    $computedStatus
                ));
            } else {
                $statement->setStatus($computedStatus);
            }

            ++$updated;

            if (!$isDryRun && 0 === ($i + 1) % $batchSize) {
                $this->entityManager->flush();
            }
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $io->success(sprintf(
            '%s %d statement(s), skipped %d (already correct)',
            $isDryRun ? 'Would update' : 'Updated',
            $updated,
            $skipped
        ));

        return Command::SUCCESS;
    }
}
