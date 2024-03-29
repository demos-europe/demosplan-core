<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\SubmitterExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
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
     * @throws Exception
     *
     * @DplanPermissions("area_admin_submitters")
     */
    #[Route(name: 'dplan_submitters_list', methods: 'GET', path: '/verfahren/{procedureId}/submitters/list')]
    public function listAction(string $procedureId): Response
    {
        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_list_submitters.html.twig',
            [
                'procedure' => $procedureId,
                'title'     => 'submitters',
            ]
        );
    }

    /**
     * @DplanPermissions("area_admin_submitters")
     */
    #[Route(name: 'dplan_admin_procedure_submitter_export', path: '/verfahren/{procedureId}/einreicher/export', methods: ['GET'], options: ['expose' => true])]
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
