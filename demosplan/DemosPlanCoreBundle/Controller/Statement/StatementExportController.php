<?php

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use demosplan\DemosPlanCoreBundle\Attribute\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\OriginalStatementCsvExporter;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OriginalStatementResourceType;
use Doctrine\ORM\Query\QueryException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class StatementExportController extends BaseController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ProcedureHandler $procedureHandler,
        private readonly NameGenerator $nameGenerator,
    ) {
    }

    /**
     * @throws UserNotFoundException
     * @throws QueryException
     * @throws Exception
     */
    #[DplanPermissions(
        'feature_admin_assessmenttable_export_statement_generic_xlsx'
    )]
    #[Route(
        path: '/verfahren/{procedureId}/abschnitte/export/csv',
        name: 'dplan_original_statement_csv_export',
        options: ['expose' => true],
        methods: 'GET'
    )]
    public function exportByStatementsFilterCsvAction(
        JsonApiActionService          $jsonApiActionService,
        OriginalStatementCsvExporter  $exporter,
        OriginalStatementResourceType $originalStatementResourceType,
        string                        $procedureId,
    ): StreamedResponse {
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

        $procedure =
            $this->procedureHandler->getProcedureWithCertainty($procedureId);
        $response->headers->set('Content-Disposition',
            $this->nameGenerator->generateDownloadFilename(
                sprintf('original_statements_%s.csv',
                    $procedure->getId())
            ));

        return $response;
    }
}


