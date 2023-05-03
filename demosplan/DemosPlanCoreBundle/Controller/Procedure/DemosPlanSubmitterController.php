<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanProcedureBundle\Logic\SubmitterExporter;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles the management for users who submit statements to a Procedure.
 */
class DemosPlanSubmitterController extends BaseController
{
    /**
     * @Route(
     *     name="dplan_submitters_list",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/submitters/list")
     *
     * @throws Exception
     *
     * @DplanPermissions("area_admin_submitters")
     */
    public function listAction(string $procedureId): Response
    {
        return $this->renderTemplate(
            '@DemosPlanProcedure/DemosPlanProcedure/administration_list_submitters.html.twig',
            [
                'procedure' => $procedureId,
                'title'     => 'submitters',
            ]
        );
    }

    /**
     * @Route(
     *      name="dplan_admin_procedure_submitter_export",
     *      path="/verfahren/{procedureId}/einreicher/export",
     *      methods={"GET"},
     *      options={"expose": true}
     * )
     *
     * @DplanPermissions("area_admin_submitters")
     */
    public function exportAction(
        Request $request,
        FileResponseGeneratorStrategy $responseGenerator,
        TranslatorInterface $translator,
        CurrentUserInterface $currentUser,
        StatementService $statementService,
        string $procedureId
    ): Response {
        try {
            $statements = $statementService->getStatementsForSubmitterExport($procedureId);
            $submitterExport = new SubmitterExporter($translator);
            $exportFile = $submitterExport->generateExport($statements, $currentUser->getUser()->getName());

            return $responseGenerator('xlsx', $exportFile);
        } catch (Exception $exception) {
            $this->logger->warning('to', [$exception]);

            return $this->redirectBack($request);
        }
    }
}
