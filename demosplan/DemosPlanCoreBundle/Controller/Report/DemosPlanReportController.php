<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Report;

use Carbon\Carbon;
use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\ServiceImporterInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Report\ExportReportService;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\PdfFile;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * Seitenausgabe Protokolldaten.
 */
class DemosPlanReportController extends BaseController
{
    /**
     * Show a report.
     *
     * @DplanPermissions("area_admin_protocol")
     *
     * @param string $procedureId
     *
     * @return Response
     *
     * @throws Exception
     */
    #[Route(name: 'dm_plan_report_table_view', path: '/report/view/{procedureId}')]
    public function viewReportAction(Request $request, $procedureId)
    {
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanReport/list.html.twig',
            [
                'title'     => 'procedure.report',
                'procedure' => $procedureId,
            ]
        );
    }

    /**
     * Generates a PDF Report for the given procedure.
     *
     * @DplanPermissions({"area_admin_protocol", "feature_export_protocol"})
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    #[Route(name: 'dplan_export_report', path: '/report/export/{procedureId}', methods: ['GET'], options: ['expose' => true])]
    public function exportProcedureReportAction(
        ExportReportService $reportService,
        NameGenerator $nameGenerator,
        PermissionsInterface $permissions,
        ProcedureHandler $procedureHandler,
        Environment $twig,
        ServiceImporterInterface $serviceImporter,
        $procedureId
    ): Response {
        $procedure = $procedureHandler->getProcedure($procedureId);
        $slugify = new Slugify();
        $pdfName = $slugify->slugify($procedure->getName()).'.pdf';

        $reportInfo = $reportService->getReportInfo($procedureId, $permissions);

        $reportMessages = $reportService->generateProcedureReport($reportInfo);

        $currentTime = Carbon::now();
        $exportedAt = [
            'exportDate' => $currentTime->format('d.m.Y'),
            'exportTime' => $currentTime->format('H:i'),
        ];

        $content = $twig->render('@DemosPlanCore/DemosPlanReport/list.procedure.report.tex.twig', [
            'procedure'    => $procedure,
            'templateVars' => $reportMessages,
            'exportedAt'   => $exportedAt,
            'title'        => 'DPlan',
        ]);
        $response = $serviceImporter->exportPdfWithRabbitMQ(base64_encode($content), []);
        $file = new PdfFile(
            $pdfName,
            base64_decode($response)
        );

        $response = new Response($file->getContent(), 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $nameGenerator->generateDownloadFilename($pdfName));

        return $response;
    }
}
