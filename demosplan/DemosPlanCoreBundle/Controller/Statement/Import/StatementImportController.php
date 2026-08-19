<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement\Import;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateInternIdException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Exception\MissingExcelDataException;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\RowAwareViolationsException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedWorksheetNameException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Import\ImportJobQueue;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelImporter;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\StatementSpreadsheetImporterWithZipSupport;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxStatementImport;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\XlsxStatementImporterFactory;
use demosplan\DemosPlanCoreBundle\Types\ImportJobType;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class StatementImportController extends BaseController
{
    private const STATEMENT_IMPORT_ENCOUNTERED_ERRORS = 'statement import failed';

    /**
     * Imports Statements from a xlsx-file.
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('feature_statements_import_excel')]
    #[Route(path: '/verfahren/{procedureId}/stellungnahmen/import', name: 'DemosPlan_statement_import', options: ['expose' => true], methods: ['POST'])]
    public function importStatements(
        FileService $fileService,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        XlsxStatementImporterFactory $importerFactory,
        ExcelImporter $excelImporter,
        string $procedureId,
        Request $request,
    ): Response {
        $requestPost = $request->request->all();
        $procedure = $procedureService->getProcedure($procedureId);

        if (!$procedure instanceof Procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        try {
            // recreate uploaded array
            $uploads = explode(',', (string) $requestPost['uploadedFiles']);
            $files = array_map($fileService->getFileInfo(...), $uploads);
            $importer = $importerFactory->createXlsxStatementImporter($excelImporter);
            $fileNames = [];
            $statementCount = 0;
            /** @var FileInfo $fileInfo */
            foreach ($files as $fileInfo) {
                $localPath = $fileService->ensureLocalFile($fileInfo->getAbsolutePath());
                $localFileInfo = new FileInfo(
                    $fileInfo->getHash(),
                    '',
                    0,
                    '',
                    $localPath,
                    $localPath,
                    null
                );
                $this->importStatementsFromXls($localFileInfo, $importer);
                $fileNames[] = $fileInfo->getFileName();
                $statementCount += count($importer->getCreatedStatements());
                $fileService->deleteFile($fileInfo->getHash());
                $fileService->deleteLocalFile($localPath);
            }
            if ($importer->hasErrors()) {
                return $this->createErrorResponse($procedureId, $importer->getErrorsAsArray());
            }
        } catch (Exception) {
            return $this->redirectToRoute(
                'DemosPlan_procedure_import',
                ['procedureId' => $procedureId]
            );
        }

        return $this->createSuccessResponse($procedureId, $statementCount, $fileNames, $permissions);
    }

    /**
     * Imports Statements from a xlsx-file inside a zip created by the assessment table export and adds related documents.
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('feature_statements_participation_import_excel')]
    #[Route(
        path: '/verfahren/{procedureId}/stellungnahmen/beteilugengsimport',
        name: 'DemosPlan_statement_participation_import',
        options: ['expose' => true],
        methods: [Request::METHOD_POST])]
    public function importParticipationStatements(
        FileService $fileService,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        XlsxStatementImporterFactory $importerFactory,
        StatementSpreadsheetImporterWithZipSupport $excelImporter,
        string $procedureId,
        Request $request,
    ): Response {
        $requestPost = $request->request->all();
        $procedure = $procedureService->getProcedure($procedureId);

        if (!$procedure instanceof Procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        try {
            // recreate uploaded array
            $uploads = explode(',', (string) $requestPost['uploadedFiles']);
            $files = array_map($fileService->getFileInfo(...), $uploads);
            $importer = $importerFactory->createXlsxStatementImporter($excelImporter);
            $fileNames = [];
            $statementsCount = 0;
            /** @var FileInfo $zipFileInfo */
            foreach ($files as $zipFileInfo) {
                $localPath = $fileService->ensureLocalFile($zipFileInfo->getAbsolutePath());
                $localFileInfo = new FileInfo(
                    $zipFileInfo->getHash(),
                    '',
                    0,
                    '',
                    $localPath,
                    $localPath,
                    null
                );
                $this->importStatementsFromXls($localFileInfo, $importer);

                $fileNames[] = $zipFileInfo->getFileName();
                $statements = $importer->getCreatedStatements();
                $statementsCount += count($statements);

                $fileService->deleteFile($zipFileInfo->getHash());
                $fileService->deleteLocalFile($localPath);
            }
            if ($importer->hasErrors()) {
                return $this->createErrorResponse($procedureId, $importer->getErrorsAsArray());
            }
        } catch (Throwable $e) {
            $this->logger->error('Something went wrong importing Statements from zip', ['exception' => $e]);

            return $this->redirectToRoute(
                'DemosPlan_procedure_import',
                ['procedureId' => $procedureId]
            );
        }

        return $this->createSuccessResponse($procedureId, $statementsCount, $fileNames, $permissions);
    }

    /**
     * Queues an import of statements from a csv-file.
     *
     * This only creates the job, {@link ImportJobProcessor} picks it up from there and the
     * user can follow its progress in the list of import jobs.
     *
     * @throws ProcedureNotFoundException
     */
    #[DplanPermissions('feature_statements_import_csv')]
    #[Route(path: '/verfahren/{procedureId}/stellungnahmen/csv-import', name: 'dplan_statement_import_csv', options: ['expose' => true], methods: ['POST'])]
    public function importStatementsFromCsv(
        CurrentProcedureService $currentProcedureService,
        CurrentUserService $currentUser,
        FileService $fileService,
        ImportJobQueue $importJobQueue,
        string $procedureId,
        Request $request,
    ): Response {
        $procedure = $currentProcedureService->getProcedure();

        if (!$procedure instanceof Procedure) {
            $this->logger->error('Failed to queue statement csv import job: procedure not found', [
                'procedureId' => $procedureId,
            ]);

            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        $uploadHashes = array_filter(explode(',', (string) $request->request->get('uploadedFiles', '')));

        foreach ($uploadHashes as $uploadHash) {
            try {
                $fileName = $fileService->getFileInfo($uploadHash)->getFileName();
            } catch (Exception $e) {
                $this->logger->error('Failed to queue statement csv import job', [
                    'uploadHash' => $uploadHash,
                    'exception'  => $e->getMessage(),
                    'trace'      => $e->getTraceAsString(),
                ]);
                $this->getMessageBag()->add('error', 'error.statements.import.file.not.found');

                continue;
            }

            if ('csv' !== mb_strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
                $this->getMessageBag()->add('error', 'error.statements.import.csv.wrong.format', ['fileName' => $fileName]);

                continue;
            }

            $importJobQueue->queue(
                $procedure,
                $currentUser->getUser(),
                $uploadHash,
                $fileName,
                ImportJobType::STATEMENTS,
                'confirm.statements.import.queued',
                'error.statements.import.queue.failed',
            );
        }

        return $this->redirectToRoute('DemosPlan_procedure_import', ['procedureId' => $procedureId]);
    }

    /**
     * @throws DemosException
     */
    private function importStatementsFromXls(
        FileInfo $fileInfo,
        XlsxStatementImport $importer,
    ): void {
        $splFileInfo = new SplFileInfo(
            $fileInfo->getAbsolutePath(),
            '',
            $fileInfo->getHash()
        );
        try {
            $importer->importFromFile($splFileInfo);
        } catch (RowAwareViolationsException $e) {
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.document.summary',
                ['doc' => $fileInfo->getFileName()]
            );
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.line.summary',
                ['lineNr' => $e->getRow()]
            );
            foreach ($e->getViolationsAsStrings() as $error) {
                $this->getMessageBag()->add('error', $error);
            }
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (MissingDataException) {
            $this->getMessageBag()->add(
                'error',
                'error.missing.data',
                ['fileName' => $fileInfo->getFileName()]
            );
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (UnexpectedWorksheetNameException $e) {
            if ('Abschnitte' === $e->getIncomingTitle()) {
                $this->getMessageBag()->add('error', 'error.wrong.selected.importer');
            } else {
                $this->getMessageBag()->add(
                    'error',
                    'error.worksheet.name',
                    [
                        'worksheetTitle' => $e->getIncomingTitle(),
                        'expectedTitles' => $e->getExpectedTitles(),
                    ]
                );
            }
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (DuplicateInternIdException) {
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.document.duplicate.internid'
            );
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (MissingExcelDataException) {
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.missing.data',
                ['doc' => $fileInfo->getFileName()]
            );
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        } catch (Exception $e) {
            $this->logger->error(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS, ['exception' => $e]);
            $this->getMessageBag()->add(
                'error',
                'statements.import.error.document.unexpected',
                ['doc' => $fileInfo->getFileName()]
            );
            throw new DemosException(self::STATEMENT_IMPORT_ENCOUNTERED_ERRORS);
        }
    }

    /**
     * @param list<array{id: int, currentWorksheet: string, lineNumber: int, message: string}> $errors
     */
    private function createErrorResponse(string $procedureId, array $errors): Response
    {
        return $this->render(
            '@DemosPlanCore/DemosPlanProcedure/administration_excel_import_errors.html.twig',
            [
                'procedure'  => $procedureId,
                'context'    => 'statements',
                'title'      => 'statements.import',
                'errors'     => $errors,
            ]
        );
    }

    private function createSuccessResponse(
        string $procedureId,
        int $numberOfCreatedStatements,
        array $fileNames,
        PermissionsInterface $permissions,
    ): Response {
        $this->getMessageBag()->addChoice(
            'confirm',
            'confirm.statements.imported.from.files.xlsx.format',
            ['count' => $numberOfCreatedStatements, 'fileName' => implode(', ', $fileNames), 'numbers' => (string) $numberOfCreatedStatements]
        );
        $route = 'dplan_procedure_statement_list';
        // Change redirect target if data input user
        if ($permissions->hasPermission('feature_statement_data_input_orga')) {
            $route = 'DemosPlan_statement_orga_list';
        }

        return $this->redirectToRoute(
            $route,
            ['procedureId' => $procedureId]
        );
    }
}
