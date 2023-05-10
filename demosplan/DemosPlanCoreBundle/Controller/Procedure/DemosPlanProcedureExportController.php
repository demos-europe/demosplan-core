<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Services\PdfNameService;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\ExportService;
use demosplan\DemosPlanProcedureBundle\Logic\ServiceOutput as ProcedureServiceOutput;
use Twig\Environment;

class DemosPlanProcedureExportController extends DemosPlanProcedureController
{
    private PdfNameService $pdfNameService;

public function __construct(
    EntityFetcher $entityFetcher,
    AssessmentHandler $assessmentHandler,
    Environment $twig,
    PermissionsInterface $permissions,
    ProcedureHandler $procedureHandler,
    ProcedureService $procedureService,
    ProcedureServiceOutput $procedureServiceOutput,
    ProcedureTypeResourceType $procedureTypeResourceType,
    SortMethodFactory $sortMethodFactory,
    PdfNameService $pdfNameService
){
    parent::__construct($entityFetcher,
        $assessmentHandler,
        $twig,
        $permissions,
        $procedureHandler,
        $procedureService,
        $procedureServiceOutput,
        $procedureTypeResourceType,
        $sortMethodFactory);
    $this->pdfNameService = $pdfNameService;
}

    /**
     * @Route(
     *     name="DemosPlan_title_page_export.tex.twig",
     *     path="/verfahren/{procedure}/titlepage/export"
     * )
     *
     * @DplanPermissions("area_public_participation")
     *
     * @param string $procedure
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    public function titlePageExportAction(
        CurrentUserInterface $currentUser,
        PermissionsInterface $permissions,
        ProcedureServiceOutput $procedureServiceOutput,
        TranslatorInterface $translator,
        $procedure
    ) {
        // is the user permitted to view the procedure at all?
        if (!$permissions->ownsProcedure() && (!$currentUser->getUser()->isLoggedIn() || !$permissions->hasPermissionsetRead())) {
            // owning planners should always be able to export procedure
            $this->getLogger()->warning('User tried to export Procedure but does not have sufficient permissions');
            $this->getMessageBag()->add('error', 'error.export');

            return $this->redirectToRoute('core_home');
        }

        $title = 'export.titlepage';
        $pdfContent = $procedureServiceOutput->generatePdfForTitlePage($procedure, $title);
        $pdfName = $translator->trans($title, [], 'page-title');

        if ('' === $pdfContent) {
            throw new Exception('PDF-Export fehlgeschlagen');
        }

        $response = new Response($pdfContent, 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $this->pdfNameService->generateDownloadFilename($pdfName));

        return $response;
    }

    /**
     * PDF-Export der Institutionen-Liste.
     *
     * @Route(
     *     name="DemosPlan_procedure_member_index_pdf",
     *     path="/verfahren/{procedure}/einstellungen/benutzer/pdf",
     *     options={"expose": true},
     * )
     *
     * @DplanPermissions({"area_main_procedures","area_admin_invitable_institution"})
     *
     * @param string $procedure
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    public function administrationMemberListPdfAction(
        CurrentProcedureService $currentProcedureService,
        ExportService $exportService,
        ProcedureServiceOutput $procedureServiceOutput,
        Request $request,
        $procedure
    ) {
        $requestPost = $request->request->all();
        $selectedOrgas = $request->request->get('orga_selected', []);

        // Lösche bestimmte RequestVariablen, die für den Export nicht benötigt werden
        $contentNotToTransferToPDF = ['r_emailTitle', 'r_emailCc', 'r_emailText'];
        foreach ($contentNotToTransferToPDF as $item) {
            unset($requestPost[$item]);
        }
        // Filter
        $filters = null;

        // hole Infos  für das Template
        $file = $procedureServiceOutput->generatePdfForMemberList($procedure, $filters, 'procedure.public.agency.list.export', $selectedOrgas);

        if ('' === $file) {
            throw new Exception('PDF-Export fehlgeschlagen');
        }

        $institutionListPhrase = $exportService->getInstitutionListPhrase();
        $procedureName = $currentProcedureService->getProcedureWithCertainty()->getName();
        $filename = "{$procedureName}_$institutionListPhrase.pdf";

        $response = new Response($file, 200);
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $this->pdfNameService->generateDownloadFilename($filename));

        return $response;
    }

    /**
     * Export Procedure.
     *
     * @Route(
     *     name="DemosPlan_procedure_export",
     *     path="/verfahren/{procedure}/export",
     * )
     *
     * @DplanPermissions("area_public_participation")
     *
     * @param string $procedure
     *
     * @return StreamedResponse|RedirectResponse
     *
     * @throws Exception
     */
    public function exportProcedureAction(
        CurrentUserService $currentUser,
        ExportService $exportService,
        PermissionsInterface $permissions,
        $procedure
    ) {
        $user = $currentUser->getUser();

        // is the user permitted to view the procedure at all?
        if ((!$user->isLoggedIn() || !$permissions->hasPermissionsetRead()) && !$permissions->ownsProcedure()) {
            // owning planners should always be able to export procedure
            $this->getLogger()->warning('User tried to export Procedure but does not have sufficient permissions');
            $this->getMessageBag()->add('error', 'error.export');

            return $this->redirectToRoute('core_home');
        }

        // Export der Verfahren
        // TODO: No permission seems to exist yet for "allow visibility of internal procedure name",
        // hence here a role check is done for now.
        $useExternalProcedureName = $user->hasAnyOfRoles([Role::CITIZEN, Role::GUEST]);

        return $exportService->generateProcedureExportZip([$procedure], $useExternalProcedureName);
    }
}
