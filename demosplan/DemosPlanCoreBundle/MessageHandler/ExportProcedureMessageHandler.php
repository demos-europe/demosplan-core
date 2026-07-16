<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\MessageHandler;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureExportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ExportService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Message\ExportProcedureMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Runs a procedure export (Gesamtabzug) in the background (no gateway timeout), stores the resulting
 * ZIP as a file and records the outcome on the {@link ProcedureExportJob} so the browser can poll
 * and download it.
 *
 * The existing synchronous {@link ExportService::generateProcedureExportZip()} is reused unchanged;
 * this handler only re-establishes the acting user and permissions that it would normally get from
 * the HTTP request.
 */
#[AsMessageHandler]
class ExportProcedureMessageHandler
{
    public function __construct(
        private readonly CurrentUserService $currentUserService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportService $exportService,
        private readonly FileService $fileService,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(ExportProcedureMessage $message): void
    {
        $job = $this->entityManager->find(ProcedureExportJob::class, $message->getJobId());
        if (!$job instanceof ProcedureExportJob) {
            $this->logger->error('Procedure export job not found', ['jobId' => $message->getJobId()]);

            return;
        }

        $job->setStatus(ProcedureExportJob::STATUS_PROCESSING);
        $job->setModifiedDate(new \DateTime());
        $this->entityManager->flush();

        $requestPushed = false;
        try {
            // The procedure export builds its own query parameters, but nested assessment-table
            // builders may still reach for the request/session; provide an empty one so they do not
            // fail outside an HTTP request.
            $this->pushSyntheticRequest();
            $requestPushed = true;

            $this->establishContext($message);

            $response = $this->exportService->generateProcedureExportZip(
                $message->getProcedureIds(),
                $message->useExternalProcedureName()
            );

            [$fileHash, $fileName] = $this->storeResponseAsFile($response, $message->getUserId());
            $job->setFileHash($fileHash);
            $job->setFileName($fileName);
            $job->setStatus(ProcedureExportJob::STATUS_COMPLETED);
        } catch (Throwable $e) {
            $this->logger->error('Asynchronous procedure export failed', ['jobId' => $message->getJobId(), 'exception' => $e]);
            $job->setStatus(ProcedureExportJob::STATUS_FAILED);
            $job->setErrorMessage($e->getMessage());
        } finally {
            if ($requestPushed) {
                $this->requestStack->pop();
            }
            $job->setModifiedDate(new \DateTime());
            $this->entityManager->flush();
        }
    }

    /**
     * Re-establish the acting user and permissions outside an HTTP request. The current procedure is
     * intentionally left unset: a procedure export is triggered from the procedure list and covers a
     * selection, so there is no single current procedure.
     */
    private function establishContext(ExportProcedureMessage $message): void
    {
        $user = $this->entityManager->find(User::class, $message->getUserId());
        if (!$user instanceof User) {
            throw new RuntimeException('Export job user not found: '.$message->getUserId());
        }
        $this->currentUserService->setUser($user);
        $this->permissions->initPermissions($user);
    }

    private function pushSyntheticRequest(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack->push($request);
    }

    /**
     * Materialise the streamed ZIP response into a stored file and return its hash and download name.
     * The body is captured in chunks so large exports never sit fully in memory.
     *
     * @return array{0: string, 1: string} file hash and file name
     */
    private function storeResponseAsFile(Response $response, string $userId): array
    {
        $tmpPath = (string) tempnam(sys_get_temp_dir(), 'procexport_');
        $handle = fopen($tmpPath, 'wb');

        ob_start(static function (string $buffer) use ($handle): string {
            fwrite($handle, $buffer);

            return '';
        }, 1024 * 256);
        $response->sendContent();
        ob_end_clean();
        fclose($handle);

        $fileName = $this->resolveFileName($response);
        $fileEntity = $this->fileService->saveTemporaryFile(
            $tmpPath,
            $fileName,
            $userId,
            null,
            FileService::VIRUSCHECK_NONE
        );

        return [$fileEntity->getHash(), $fileName];
    }

    private function resolveFileName(Response $response): string
    {
        $disposition = (string) $response->headers->get('Content-Disposition');
        if (1 === preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $matches)) {
            return rawurldecode(trim($matches[1], '"'));
        }

        return 'Verfahrensexport.zip';
    }
}
