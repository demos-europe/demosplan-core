<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\OriginalStatementCsvExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\OriginalStatementDocxExporter;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OriginalStatementResourceType;
use Doctrine\ORM\Query\QueryException;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementExportController extends BaseController
{

    private const OUTPUT_DESTINATION = 'php://output';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly NameGenerator $nameGenerator,
        private readonly ProcedureHandler $procedureHandler,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws QueryException
     * @throws Exception
     */
    #[DplanPermissions(
        'feature_admin_export_original_statement_csv'
    )]
    #[Route(
        path: '/verfahren/{procedureId}/originalStellungnahme/export/csv',
        name: 'dplan_original_statement_csv_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportByStatementsFilterCsvAction(
        JsonApiActionService $jsonApiActionService,
        OriginalStatementCsvExporter $exporter,
        OriginalStatementResourceType $originalStatementResourceType,
    ): StreamedResponse {
        // Filter parameters (procedureId, statementIds, etc.) are extracted from the current request query parameters
        // by the JsonApiActionService rather than being passed as explicit method parameters
        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $jsonApiActionService->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $originalStatementResourceType
            )->getList()
        );

        $response = new StreamedResponse(
            static function () use ($statementEntities, $exporter) {
                echo $exporter->export($statementEntities);
            }
        );

        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set(
            'Content-Type',
            'text/csv; charset=utf-8'
        );

        $filename = $this->translator->trans('statements.original').'-'.
            Carbon::now('Europe/Berlin')->format('d-m-Y-H:i').'.csv';

        $response->headers->set('Content-Disposition',
            $this->nameGenerator->generateDownloadFilename(
                $filename
            ));

        return $response;
    }

    #[Route(
        path: '/verfahren/{procedureId}/originalStellungnahme/export/docx',
        name: 'dplan_original_statement_docx_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportByStatementsFilterDocxAction(
        JsonApiActionService $jsonApiActionService,
        OriginalStatementDocxExporter $exporter,
        OriginalStatementResourceType $originalStatementResourceType,
        CurrentProcedureService $currentProcedureService,
    )
    {

        /** @var Statement[] $statementEntities */
        $statementEntities = array_values(
            $jsonApiActionService->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $originalStatementResourceType
            )->getList()
        );
        $currentProcedure = $currentProcedureService->getProcedure();

        $response = new StreamedResponse(
            static function () use (
                $statementEntities,
                $currentProcedure,
                $exporter) {
                $exportedDoc = $exporter->export($statementEntities, $currentProcedure);
                $exportedDoc->save(self::OUTPUT_DESTINATION);
            }
        );

        $this->setResponseHeaders($response, 'original.docx');

        return $response;


    }

    private function setResponseHeaders(
        StreamedResponse $response,
        string $filename,
    ): void {
        $response->headers->set('Pragma', 'public');
        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document; charset=utf-8'
        );
        $response->headers->set('Content-Disposition', $this->nameGenerator->generateDownloadFilename($filename));
    }
}
