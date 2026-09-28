<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

#[AsCommand(
    name: 'dplan:files:fix-procedure-mismatches',
    description: 'Find and (with --apply) fix _elements/_single_doc references pointing at files owned by a different procedure. Dry-run by default.',
)]
class FixProcedureFileMismatchesCommand extends CoreCommand
{
    private const BATCH_SIZE = 50;

    private const OUTCOME_FIXED = 'fixed';
    private const OUTCOME_SKIPPED = 'skipped';

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService $fileService,
        private readonly LoggerInterface $logger,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Actually perform the fix. Without this flag the command only reports what would change.'
            )
            ->addOption(
                'procedure',
                null,
                InputOption::VALUE_REQUIRED,
                'Restrict to a single owning procedure ID.'
            )
            ->addOption(
                'include-deleted-owners',
                null,
                InputOption::VALUE_NONE,
                'Also process references whose owning procedure is soft-deleted (skipped by default).'
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'Process at most N references per table (useful for staged rollout).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $apply = (bool) $input->getOption('apply');
        $procedureId = $input->getOption('procedure');
        $includeDeleted = (bool) $input->getOption('include-deleted-owners');
        $limit = null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null;

        $io->title('Procedure/file mismatch '.($apply ? 'fix' : 'audit (dry-run)'));

        $elementIds = $this->findMismatchedReferenceIds(
            '_elements',
            '_e_id',
            '_e_file',
            '_e_deleted',
            $procedureId,
            $includeDeleted,
            $limit
        );
        $singleDocIds = $this->findMismatchedReferenceIds(
            '_single_doc',
            '_sd_id',
            '_sd_document',
            '_sd_deleted',
            $procedureId,
            $includeDeleted,
            $limit
        );

        $io->section('Discovery');
        $io->table(
            ['Reference table', 'Broken references'],
            [
                ['_elements', count($elementIds)],
                ['_single_doc', count($singleDocIds)],
            ]
        );

        if ([] === $elementIds && [] === $singleDocIds) {
            $io->success('Nothing to fix.');

            return Command::SUCCESS;
        }

        $this->reportByOwningProcedure($io, $elementIds, $singleDocIds);

        if (!$apply) {
            $io->note('Dry-run only. No changes made. Re-run with --apply to fix.');

            return Command::SUCCESS;
        }

        if ($input->isInteractive() && !$io->confirm(
            sprintf(
                'About to fix %d _elements and %d _single_doc references. Continue?',
                count($elementIds),
                count($singleDocIds)
            ),
            false
        )) {
            $io->warning('Aborted.');

            return Command::SUCCESS;
        }

        $io->section('Applying fixes');
        $elementsResult = $this->applyFixes($io, $elementIds, fn (string $id): string => $this->fixElement($id));
        $singleDocsResult = $this->applyFixes($io, $singleDocIds, fn (string $id): string => $this->fixSingleDoc($id));

        $io->section('Summary');
        $io->table(
            ['Reference table', 'Fixed', 'Skipped (file missing)', 'Errors'],
            [
                ['_elements',   $elementsResult['fixed'],   $elementsResult['skipped'],   $elementsResult['errors']],
                ['_single_doc', $singleDocsResult['fixed'], $singleDocsResult['skipped'], $singleDocsResult['errors']],
            ]
        );

        if ($elementsResult['errors'] > 0 || $singleDocsResult['errors'] > 0) {
            $io->warning('Completed with errors. Inspect the log for details.');

            return Command::FAILURE;
        }

        $io->success('Done.');

        return Command::SUCCESS;
    }

    /**
     * Finds references in $table whose embedded file ident resolves to a _files
     * row owned by a different procedure than the reference's own procedure.
     *
     * The reference is stored as a colon-delimited file string (see
     * {@see File::getFileString()}: "filename:ident:size:mimetype"). The nested
     * SUBSTRING_INDEX calls peel the ident (2nd field) off the right-hand end, so
     * a filename containing ":" does not shift the result. Keep the extraction in
     * sync with that format.
     *
     * @return list<string>
     */
    private function findMismatchedReferenceIds(
        string $table,
        string $idColumn,
        string $fileColumn,
        string $deletedColumn,
        ?string $procedureId,
        bool $includeDeleted,
        ?int $limit,
    ): array {
        // Identifiers are internal constants (never user input); $limit is cast to
        // int by the caller, so interpolating them is safe.
        $sql = sprintf(
            'SELECT ref.%1$s
             FROM %2$s ref
             JOIN _files f
               ON f._f_ident = SUBSTRING_INDEX(SUBSTRING_INDEX(ref.%3$s, ":", -3), ":", 1)
             JOIN _procedure p
               ON p._p_id = ref._p_id
             WHERE ref.%4$s = 0
               AND f._f_deleted = 0
               AND ref._p_id <> f.procedure_id',
            $idColumn,
            $table,
            $fileColumn,
            $deletedColumn
        );
        $params = [];

        if (!$includeDeleted) {
            $sql .= ' AND p._p_deleted = 0';
        }
        if (null !== $procedureId) {
            $sql .= ' AND ref._p_id = :pid';
            $params['pid'] = $procedureId;
        }
        if (null !== $limit) {
            $sql .= ' LIMIT '.$limit;
        }

        return $this->connection->fetchFirstColumn($sql, $params);
    }

    /**
     * @param list<string> $elementIds
     * @param list<string> $singleDocIds
     */
    private function reportByOwningProcedure(SymfonyStyle $io, array $elementIds, array $singleDocIds): void
    {
        $unionParts = [];
        $params = [];
        $types = [];

        if ([] !== $elementIds) {
            $unionParts[] = 'SELECT _p_id, COUNT(*) AS cnt FROM _elements WHERE _e_id IN (:eids) GROUP BY _p_id';
            $params['eids'] = $elementIds;
            $types['eids'] = ArrayParameterType::STRING;
        }
        if ([] !== $singleDocIds) {
            $unionParts[] = 'SELECT _p_id, COUNT(*) AS cnt FROM _single_doc WHERE _sd_id IN (:sdids) GROUP BY _p_id';
            $params['sdids'] = $singleDocIds;
            $types['sdids'] = ArrayParameterType::STRING;
        }
        if ([] === $unionParts) {
            return;
        }

        $sql = 'SELECT p._p_id      AS proc_id,
                       p._p_name    AS proc_name,
                       p._p_master  AS is_blueprint,
                       p._p_deleted AS deleted,
                       SUM(refs.cnt) AS broken
                FROM ('.implode(' UNION ALL ', $unionParts).') refs
                JOIN _procedure p ON p._p_id = refs._p_id
                GROUP BY p._p_id, p._p_name, p._p_master, p._p_deleted
                ORDER BY broken DESC
                LIMIT 30';

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        $io->section('Top owning procedures with broken refs');
        $io->table(
            ['Procedure ID', 'Name', 'Blaupause', 'Deleted', 'Broken refs'],
            array_map(
                static fn (array $r): array => [
                    $r['proc_id'],
                    mb_substr((string) $r['proc_name'], 0, 50),
                    $r['is_blueprint'] ? 'yes' : '',
                    $r['deleted'] ? 'yes' : '',
                    (int) $r['broken'],
                ],
                $rows
            )
        );
    }

    /**
     * Runs $fixOne for each id, owning the shared bookkeeping: progress bar,
     * batched flush/clear, per-reference error isolation and outcome counters.
     * $fixOne returns self::OUTCOME_FIXED or self::OUTCOME_SKIPPED and may throw
     * to mark a reference as failed.
     *
     * @param list<string>             $ids
     * @param callable(string): string $fixOne
     *
     * @return array{fixed: int, skipped: int, errors: int}
     */
    private function applyFixes(SymfonyStyle $io, array $ids, callable $fixOne): array
    {
        $fixed = $skipped = $errors = 0;
        $io->progressStart(count($ids));

        foreach ($ids as $i => $id) {
            try {
                if (self::OUTCOME_SKIPPED === $fixOne($id)) {
                    ++$skipped;
                } else {
                    ++$fixed;
                }
            } catch (Throwable $e) {
                ++$errors;
                $this->logger->error('Failed to fix file reference', [
                    'id'        => $id,
                    'exception' => $e,
                ]);
            }

            if (0 === ($i + 1) % self::BATCH_SIZE) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $this->entityManager->clear();
        $io->progressFinish();

        return ['fixed' => $fixed, 'skipped' => $skipped, 'errors' => $errors];
    }

    private function fixElement(string $id): string
    {
        $element = $this->entityManager->find(Elements::class, $id);
        if (!$element instanceof Elements) {
            throw new RuntimeException(sprintf('Element %s not found', $id));
        }

        $newFile = $this->fileService->createCopyOfFile($element->getFile(), $element->getProcedure()->getId());
        if (null === $newFile) {
            $this->logger->warning('Skipped element — source file missing', [
                '_e_id'   => $id,
                'oldFile' => $element->getFile(),
            ]);

            return self::OUTCOME_SKIPPED;
        }

        $element->setFile($newFile->getFileString());

        return self::OUTCOME_FIXED;
    }

    private function fixSingleDoc(string $id): string
    {
        $singleDoc = $this->entityManager->find(SingleDocument::class, $id);
        if (!$singleDoc instanceof SingleDocument) {
            throw new RuntimeException(sprintf('SingleDocument %s not found', $id));
        }

        $newFile = $this->fileService->createCopyOfFile($singleDoc->getDocument(), $singleDoc->getProcedure()->getId());
        if (null === $newFile) {
            $this->logger->warning('Skipped single_doc — source file missing', [
                '_sd_id'  => $id,
                'oldFile' => $singleDoc->getDocument(),
            ]);

            return self::OUTCOME_SKIPPED;
        }

        $singleDoc->setDocument($newFile->getFileString());

        return self::OUTCOME_FIXED;
    }
}
