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

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatementImportController extends BaseController
{
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
        EntityManagerInterface $entityManager,
        FileService $fileService,
        string $procedureId,
        Request $request,
    ): Response {
        $procedure = $currentProcedureService->getProcedure();

        if (!$procedure instanceof Procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        $uploadHashes = array_filter(explode(',', (string) $request->request->get('uploadedFiles', '')));

        foreach ($uploadHashes as $uploadHash) {
            $this->queueCsvStatementImportJob($uploadHash, $procedure, $currentUser, $entityManager, $fileService);
        }

        return $this->redirectToRoute('DemosPlan_procedure_import', ['procedureId' => $procedureId]);
    }

    private function queueCsvStatementImportJob(
        string $uploadHash,
        Procedure $procedure,
        CurrentUserService $currentUser,
        EntityManagerInterface $entityManager,
        FileService $fileService,
    ): void {
        $fileName = '';
        $job = new ImportJob();

        try {
            $fileName = $fileService->getFileInfo($uploadHash)->getFileName();

            if ('csv' !== mb_strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
                $this->getMessageBag()->add('error', 'error.statements.import.csv.wrong.format', ['fileName' => $fileName]);

                return;
            }

            $job->setProcedure($procedure);
            $job->setUser($currentUser->getUser());
            $job->setImportType(ImportJob::TYPE_STATEMENTS);
            $job->setFilePath($uploadHash);
            $job->setFileName($fileName);

            // capture the current organisation context for background processing
            $currentOrga = $currentUser->getUser()->getCurrentOrganisation();
            if ($currentOrga instanceof Orga) {
                $job->setOrganisation($currentOrga);
            }

            $entityManager->persist($job);
            $entityManager->flush();

            $this->logger->info('Statement csv import job queued', [
                'jobId'       => $job->getId(),
                'fileName'    => $fileName,
                'procedureId' => $procedure->getId(),
            ]);

            $this->getMessageBag()->add(
                'confirm',
                'confirm.statements.import.queued',
                [
                    'fileName' => $fileName,
                    'jobId'    => $job->getId(),
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to queue statement csv import job', [
                'fileName'  => $fileName,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            $this->getMessageBag()->add(
                'error',
                'error.statements.import.queue.failed',
                ['fileName' => $fileName]
            );
        }
    }
}
