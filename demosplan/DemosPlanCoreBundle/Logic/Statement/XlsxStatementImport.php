<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Statement\ManualOriginalStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\RowAwareViolationsException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelImporter;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

class XlsxStatementImport
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ExcelImporter
     */
    private $xlsxStatementImporter;

    /**
     * @var StatementService
     */
    private $statementService;

    /**
     * @var EventDispatcherPostInterface
     */
    private $eventDispatcher;

    /**
     * @var StatementRepository
     */
    private $statementRepository;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var Permissions
     */
    private $permissions;

    /**
     * @var array
     */
    private $createdStatements;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    public function __construct(
        CurrentUserInterface $currentUser,
        EventDispatcherPostInterface $eventDispatcher,
        ExcelImporter $xlsxStatementImporter,
        LoggerInterface $logger,
        StatementRepository $statementRepository,
        StatementService $statementService,
        EntityManagerInterface $entityManager,
        Permissions $permissions
    ) {
        $this->xlsxStatementImporter = $xlsxStatementImporter;
        $this->statementService = $statementService;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->statementRepository = $statementRepository;
        $this->entityManager = $entityManager;
        $this->permissions = $permissions;
        $this->createdStatements = [];
        $this->currentUser = $currentUser;
    }

    /**
     * Import statements from excel document, which is located in the given FileInfo.
     * The extracted statements will be validated, persisted and indexed.
     * Also report-entries will be generated and the StatementCreatedEvent dispatched.
     * In case of an occurring error on generating the statements, the process will continued to getting all
     * invalid cases and therefore allow to return collection of errors.
     * The generated Statements will only be persisted, if the document was processed without an error.
     *
     * @param FileInfo $file Hands over basic information about the file
     *
     * @throws Exception
     * @throws RowAwareViolationsException
     * @throws ConnectionException|UnexpectedWorksheetNameException
     */
    public function importFromFile(FileInfo $file): void
    {
        $fileInfo = new SplFileInfo($file->getAbsolutePath(), '', $file->getHash());
        $this->createdStatements = [];

        // allow to rollback all in case of error
        $doctrineConnection = $this->entityManager->getConnection();
        try {
            $doctrineConnection->beginTransaction();
            $this->xlsxStatementImporter->process($fileInfo);
            array_map([$this->entityManager, 'persist'], $this->xlsxStatementImporter->getGeneratedTags());
            $generatedStatements = $this->xlsxStatementImporter->getGeneratedStatements();
            if ($this->hasErrors()) {
                $doctrineConnection->rollBack();

                return;
            }

            foreach ($generatedStatements as $statement) {
                $this->statementRepository->addObject($statement);

                try {
                    $statementArray = $this->statementService->convertToLegacy($statement);
                    $this->statementService->addReportNewStatement($statementArray);
                } catch (Exception $exception) {
                    $doctrineConnection->rollBack();
                    $this->logger->warning('Add Report on importFromHash() failed Message: ', [$exception]);
                    throw $exception;
                }

                /** @var StatementCreatedEvent $statementCreatedEvent */
                $statementCreatedEvent = $this->eventDispatcher->post(new ManualOriginalStatementCreatedEvent($statement));

                // inform user about statement similarities is not necessary

                $this->createdStatements[] = $statementCreatedEvent->getStatement();
            }
        } catch (Exception $exception) {
            $doctrineConnection->rollBack();
            throw $exception;
        }
        $doctrineConnection->commit();
    }

    public function hasErrors(): bool
    {
        return $this->xlsxStatementImporter->hasErrors();
    }

    /**
     * @return array<int, Statement>
     */
    public function getCreatedStatements(): array
    {
        return $this->createdStatements;
    }

    /**
     * @return array<int, array>
     */
    public function getErrorsAsArray(): array
    {
        return $this->xlsxStatementImporter->getErrorsAsArray();
    }
}
