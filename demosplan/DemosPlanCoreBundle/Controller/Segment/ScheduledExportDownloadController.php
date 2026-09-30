<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ScheduledExportJob;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobDownloadResponseFactory;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Landing page and file endpoint for a scheduled Synopse export's download link, sent by mail once
 * the export has finished.
 *
 * Split into two routes deliberately: the landing page renders HTML, so a human opening the mail
 * link on a device that is not logged in sees a real page - including a sensible message for a
 * failed export - while {@see downloadFile()} only ever streams raw file bytes. Merging both into
 * one action would make it decide, per request, whether to return a page or a download.
 */
class ScheduledExportDownloadController extends BaseController
{
    #[DplanPermissions('feature_admin_scheduled_xlsx_export')]
    #[Route(
        path: '/xlsx-export/geplant/{jobId}',
        name: 'DemosPlan_scheduled_export_download_page',
        methods: ['GET']
    )]
    public function downloadPage(
        CurrentUserService $currentUserService,
        EntityManagerInterface $entityManager,
        string $jobId,
    ): Response {
        $job = $this->findOwnJob($entityManager, $currentUserService, $jobId);

        return $this->render('@DemosPlanCore/Export/scheduled_export_download.html.twig', [
            'job'         => $job,
            'downloadUrl' => $this->generateUrl('DemosPlan_scheduled_export_file_download', ['jobId' => $job->getId()]),
        ]);
    }

    #[DplanPermissions('feature_admin_scheduled_xlsx_export')]
    #[Route(
        path: '/xlsx-export/geplant/{jobId}/datei',
        name: 'DemosPlan_scheduled_export_file_download',
        methods: ['GET']
    )]
    public function downloadFile(
        CurrentUserService $currentUserService,
        EntityManagerInterface $entityManager,
        ExportJobDownloadResponseFactory $downloadResponseFactory,
        string $jobId,
    ): Response {
        $job = $this->findOwnJob($entityManager, $currentUserService, $jobId);
        if (ScheduledExportJob::STATUS_COMPLETED !== $job->getStatus()) {
            throw new NotFoundHttpException();
        }

        return $downloadResponseFactory->createForJob($job) ?? throw new NotFoundHttpException();
    }

    /**
     * Same ownership check on both routes: a job belongs to exactly the user whose schedule produced
     * it. DplanPermissions above only proves the visitor is logged in and holds the export permission
     * in general - it says nothing about whether this particular file is theirs.
     */
    private function findOwnJob(
        EntityManagerInterface $entityManager,
        CurrentUserService $currentUserService,
        string $jobId,
    ): ScheduledExportJob {
        $job = $entityManager->find(ScheduledExportJob::class, $jobId);
        if (!$job instanceof ScheduledExportJob || $job->getUserId() !== $currentUserService->getUser()->getId()) {
            throw new NotFoundHttpException();
        }

        return $job;
    }
}
