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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Statement\OriginalStatementCsvExporter;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OriginalStatementResourceType;
use Doctrine\ORM\Query\QueryException;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementExportController extends BaseController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
        private readonly NameGenerator $nameGenerator,
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
        string $procedureId,
        ?array $statementIds,
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

        $filename = sprintf(
            $this->translator->trans('statements.original').'-%s.csv',
            Carbon::now('Europe/Berlin')->format('d-m-Y-H:i')
        );

        $response->headers->set('Content-Disposition',
            $this->nameGenerator->generateDownloadFilename(
                $filename
            ));

        return $response;
    }
}
