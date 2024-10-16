<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Controller\Statement;


use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureCoupleTokenFetcher;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StatementListController extends BaseController
{
    /**
     * List all statements per procedure without any possibilities to edit.
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('area_admin_statement_list')]
    #[Route(
        path: '/verfahren/{procedureId}/einwendungen',
        name: 'dplan_procedure_statement_list',
        options: ['expose' => true],
        methods: ['GET']
    )]
    public function readOnlyStatementListAction(
        string $procedureId,
        ProcedureCoupleTokenFetcher $tokenFetcher,
        ProcedureService $procedureService
    ): Response {
        $procedure = $procedureService->getProcedure($procedureId);

        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        $isSourceAndCoupledProcedure = $tokenFetcher->isSourceAndCoupledProcedure($procedure);

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/list_statements.html.twig',
            [
                'procedureId'    => $procedureId,
                'title'        => 'statements',
                'templateVars' => [
                    'isSourceAndCoupledProcedure' => $isSourceAndCoupledProcedure,
                ],
            ]
        );
    }

    /**
     * List all original statements per procedure.
     *
     * @throws ProcedureNotFoundException
     * @throws Exception
     */
    #[DplanPermissions('area_admin_statement_list')] // todo: wrap in a new permission area_admin_original_statement_list
    #[Route(
        path: '/verfahren/{procedureId}/original-einwendungen',
        name: 'dplan_procedure_original_statement_list',
        options: ['expose' => true],
        methods: ['GET']
    )]
    public function readOnlyOriginalStatementListAction(
        string $procedureId,
        ProcedureService $procedureService
    ): Response {
        $procedure = $procedureService->getProcedure($procedureId);

        if (null === $procedure) {
            throw ProcedureNotFoundException::createFromId($procedureId);
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/list_original_statements.html.twig',
            [
                'procedureId' => $procedureId,
                'title'     => 'statements.original',
            ]
        );
    }
}
