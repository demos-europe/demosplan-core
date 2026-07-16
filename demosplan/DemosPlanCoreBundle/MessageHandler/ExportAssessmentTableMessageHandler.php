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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableExporterStrategy;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Message\ExportAssessmentTableMessage;
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
 * Runs an Abwägungstabelle export in the background (no gateway timeout), stores the result as a
 * file and records the outcome on the {@link AssessmentTableExportJob} so the browser can poll and
 * download it.
 *
 * The existing synchronous exporter is reused unchanged; this handler only re-establishes the
 * request-scoped context (session filter hash, current user, current procedure, permissions) that
 * the exporter would normally get from the HTTP request.
 */
#[AsMessageHandler]
class ExportAssessmentTableMessageHandler
{
    public function __construct(
        private readonly AssessmentTableExporterStrategy $assessmentExporter,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserService $currentUserService,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileResponseGeneratorStrategy $responseGenerator,
        private readonly FileService $fileService,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedureService $procedureService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(ExportAssessmentTableMessage $message): void
    {
        $job = $this->entityManager->find(AssessmentTableExportJob::class, $message->getJobId());
        if (!$job instanceof AssessmentTableExportJob) {
            $this->logger->error('Assessment table export job not found', ['jobId' => $message->getJobId()]);

            return;
        }

        $job->setStatus(AssessmentTableExportJob::STATUS_PROCESSING);
        $job->setModifiedDate(new \DateTime());
        $this->entityManager->flush();

        $requestPushed = false;
        try {
            // Rebuild the request/session the exporter relies on (only the filter hash list is
            // genuinely request-scoped; everything else is reloaded from the database below).
            $this->pushSyntheticRequest($message->getHashList());
            $requestPushed = true;

            $this->establishContext($message);

            $file = $this->assessmentExporter->export($message->getExportFormat(), $message->getParameters());
            $response = ($this->responseGenerator)($message->getExportFormat(), $file);

            [$fileHash, $fileName] = $this->storeResponseAsFile($response, $message);
            $job->setFileHash($fileHash);
            $job->setFileName($fileName);
            $job->setStatus(AssessmentTableExportJob::STATUS_COMPLETED);
        } catch (Throwable $e) {
            $this->logger->error('Asynchronous assessment table export failed', ['jobId' => $message->getJobId(), 'exception' => $e]);
            $job->setStatus(AssessmentTableExportJob::STATUS_FAILED);
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
     * Re-establish the acting user, current procedure and permissions outside an HTTP request.
     */
    private function establishContext(ExportAssessmentTableMessage $message): void
    {
        $user = $this->entityManager->find(User::class, $message->getUserId());
        if (!$user instanceof User) {
            throw new RuntimeException('Export job user not found: '.$message->getUserId());
        }
        $this->currentUserService->setUser($user);
        $this->permissions->initPermissions($user);

        $procedure = $this->procedureService->getProcedure($message->getProcedureId());
        if (!$procedure instanceof Procedure) {
            throw new RuntimeException('Export job procedure not found: '.$message->getProcedureId());
        }
        $this->currentProcedureService->setProcedure($procedure);
        $this->permissions->setProcedure($procedure);
    }

    private function pushSyntheticRequest(array $hashList): void
    {
        $session = new Session(new MockArraySessionStorage());
        if ([] !== $hashList) {
            $session->set('hashList', $hashList);
        }
        $request = new Request();
        $request->setSession($session);
        $this->requestStack->push($request);
    }

    /**
     * Materialise the (possibly streamed) response body into a stored file and return its hash and
     * download name. The body is captured in chunks so large zips never sit fully in memory.
     *
     * @return array{0: string, 1: string} file hash and file name
     */
    private function storeResponseAsFile(Response $response, ExportAssessmentTableMessage $message): array
    {
        $tmpPath = (string) tempnam(sys_get_temp_dir(), 'atexport_');
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
            $message->getUserId(),
            $message->getProcedureId(),
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

        return 'export';
    }
}
