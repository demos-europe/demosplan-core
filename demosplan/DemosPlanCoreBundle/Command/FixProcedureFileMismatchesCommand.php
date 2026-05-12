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
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;

class FixProcedureFileMismatchesCommand extends CoreCommand
{
    protected static $defaultName = 'dplan:files:fix-procedure-mismatches';

    protected static $defaultDescription = 'Find and (with --apply) fix _elements/_single_doc references pointing at files owned by a different procedure (HDDP-18). Dry-run by default.';

    private const BATCH_SIZE = 50;

    public function __construct(
        ParameterBagInterface $parameterBag,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService $fileService,
        private readonly LoggerInterface $logger,
        string $name = null,
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

        $apply          = (bool) $input->getOption('apply');
        $procedureId    = $input->getOption('procedure');
        $includeDeleted = (bool) $input->getOption('include-deleted-owners');
        $limit          = null !== $input->getOption('limit') ? (int) $input->getOption('limit') : null;

        $io->title('HDDP-18 — procedure/file mismatch '.($apply ? 'fix' : 'audit (dry-run)'));

        $elementIds   = $this->findMismatchedElementIds($procedureId, $includeDeleted, $limit);
        $singleDocIds = $this->findMismatchedSingleDocIds($procedureId, $includeDeleted, $limit);

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
        $elementsResult   = $this->fixElements($io, $elementIds);
        $singleDocsResult = $this->fixSingleDocs($io, $singleDocIds);

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
     * @return list<string>
     */
    private function findMismatchedElementIds(?string $procedureId, bool $includeDeleted, ?int $limit): array
    {
        $sql = 'SELECT e._e_id
                FROM _elements e
                JOIN _files f
                  ON f._f_ident = SUBSTRING_INDEX(SUBSTRING_INDEX(e._e_file, ":", -3), ":", 1)
                JOIN _procedure p
                  ON p._p_id = e._p_id
                WHERE e._e_deleted = 0
                  AND f._f_deleted = 0
                  AND e._p_id <> f.procedure_id';
        $params = [];

        if (!$includeDeleted) {
            $sql .= ' AND p._p_deleted = 0';
        }
        if (null !== $procedureId) {
            $sql .= ' AND e._p_id = :pid';
            $params['pid'] = $procedureId;
        }
        if (null !== $limit) {
            $sql .= ' LIMIT '.$limit;
        }

        return $this->connection->fetchFirstColumn($sql, $params);
    }

    /**
     * @return list<string>
     */
    private function findMismatchedSingleDocIds(?string $procedureId, bool $includeDeleted, ?int $limit): array
    {
        $sql = 'SELECT sd._sd_id
                FROM _single_doc sd
                JOIN _files f
                  ON f._f_ident = SUBSTRING_INDEX(SUBSTRING_INDEX(sd._sd_document, ":", -3), ":", 1)
                JOIN _procedure p
                  ON p._p_id = sd._p_id
                WHERE sd._sd_deleted = 0
                  AND f._f_deleted = 0
                  AND sd._p_id <> f.procedure_id';
        $params = [];

        if (!$includeDeleted) {
            $sql .= ' AND p._p_deleted = 0';
        }
        if (null !== $procedureId) {
            $sql .= ' AND sd._p_id = :pid';
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
        $params     = [];
        $types      = [];

        if ([] !== $elementIds) {
            $unionParts[]   = 'SELECT _p_id, COUNT(*) AS cnt FROM _elements WHERE _e_id IN (:eids) GROUP BY _p_id';
            $params['eids'] = $elementIds;
            $types['eids']  = ArrayParameterType::STRING;
        }
        if ([] !== $singleDocIds) {
            $unionParts[]    = 'SELECT _p_id, COUNT(*) AS cnt FROM _single_doc WHERE _sd_id IN (:sdids) GROUP BY _p_id';
            $params['sdids'] = $singleDocIds;
            $types['sdids']  = ArrayParameterType::STRING;
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
     * @param list<string> $ids
     *
     * @return array{fixed:int,skipped:int,errors:int}
     */
    private function fixElements(SymfonyStyle $io, array $ids): array
    {
        $fixed = $skipped = $errors = 0;
        $io->progressStart(count($ids));

        foreach ($ids as $i => $id) {
            try {
                /** @var Elements|null $element */
                $element = $this->entityManager->find(Elements::class, $id);
                if (null === $element) {
                    ++$errors;
                    $this->logger->error('Element not found during HDDP-18 fix', ['_e_id' => $id]);
                    continue;
                }

                $newFile = $this->fileService->createCopyOfFile(
                    $element->getFile(),
                    $element->getProcedure()->getId()
                );
                if (null === $newFile) {
                    ++$skipped;
                    $this->logger->warning('Skipped element — source file missing', [
                        '_e_id'   => $id,
                        'oldFile' => $element->getFile(),
                    ]);
                    continue;
                }

                $element->setFile($newFile->getFileString());
                ++$fixed;
            } catch (Throwable $e) {
                ++$errors;
                $this->logger->error('Failed to fix element', [
                    '_e_id'     => $id,
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

    /**
     * @param list<string> $ids
     *
     * @return array{fixed:int,skipped:int,errors:int}
     */
    private function fixSingleDocs(SymfonyStyle $io, array $ids): array
    {
        $fixed = $skipped = $errors = 0;
        $io->progressStart(count($ids));

        foreach ($ids as $i => $id) {
            try {
                /** @var SingleDocument|null $sd */
                $sd = $this->entityManager->find(SingleDocument::class, $id);
                if (null === $sd) {
                    ++$errors;
                    $this->logger->error('SingleDocument not found during HDDP-18 fix', ['_sd_id' => $id]);
                    continue;
                }

                $newFile = $this->fileService->createCopyOfFile(
                    $sd->getDocument(),
                    $sd->getProcedure()->getId()
                );
                if (null === $newFile) {
                    ++$skipped;
                    $this->logger->warning('Skipped single_doc — source file missing', [
                        '_sd_id'  => $id,
                        'oldFile' => $sd->getDocument(),
                    ]);
                    continue;
                }

                $sd->setDocument($newFile->getFileString());
                ++$fixed;
            } catch (Throwable $e) {
                ++$errors;
                $this->logger->error('Failed to fix single_doc', [
                    '_sd_id'    => $id,
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
}
