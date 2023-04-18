<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Report;

use Carbon\Carbon;
use Cocur\Slugify\Slugify;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\Report\ExportReportService;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use Exception;
use PhpOffice\PhpWord\Settings;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Seitenausgabe Protokolldaten.
 */
class DemosPlanReportController extends BaseController
{
    /**
     * Show a report.
     *
     * @Route(
     *     name="dm_plan_report_table_view",
     *     path="/report/view/{procedureId}"
     * )
     * @DplanPermissions("area_admin_protocol")
     *
     * @param string $procedureId
     *
     * @return Response
     *
     * @throws Exception
     */
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
     * @Route(
     *     name="dplan_export_report",
     *     path="/report/export/{procedureId}",
     *     methods={"GET"},
     *     options={"expose": true},
     * )
     * @DplanPermissions({"area_admin_protocol", "feature_export_protocol"})
     *
     * @param string $procedureId
     *
     * @throws Exception
     */
    public function exportProcedureReportAction(
        ExportReportService $reportService,
        ParameterBagInterface $parameterBag,
        PermissionsInterface $permissions,
        ProcedureHandler $procedureHandler,
        $procedureId
    ): Response {
        $slugify = new Slugify();
        $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);

        $currentTime = Carbon::now();
        $reportMeta = [
            'name'       => $procedure->getName(),
            'exportDate' => $currentTime->format('d.m.Y'),
            'exportTime' => $currentTime->format('H:i'),
        ];

        Settings::setPdfRendererPath($parameterBag->get('pdf_renderer_path'));
        Settings::setPdfRendererName($parameterBag->get('pdf_renderer_name'));

        $response = new StreamedResponse(
            static function () use ($procedureId, $reportMeta, $reportService, $permissions) {
                $reportInfo = $reportService->getReportInfo($procedureId, $permissions);
                $pdfReport = $reportService->generateProcedureReport($reportInfo, $reportMeta);
                $pdfReport->save('php://output');
            }
        );

        $pdfName = $slugify->slugify($procedure->getName()).'.pdf';
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf; charset=utf-8');
        $response->headers->set('Content-Disposition', $this->generateDownloadFilename($pdfName));

        return $response;
    }
}
