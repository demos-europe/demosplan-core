<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\AnnotatedStatementPdf;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\APIController;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Transformers\PercentageDistributionTransformer;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfService;
use Symfony\Component\Routing\Annotation\Route;


class AnnotatedStatementPdfPercentageDistributionApiController extends APIController
{
    /**
     * @Route(path="/api/1.0/AnnotatedStatementPdfPercentageDistribution/",
     *        methods={"GET"},
     *        name="dplan_api_list_percentage_distribution",
     *        options={"expose": true})
     *
     * @DplanPermissions("feature_import_statement_pdf")
     *
     * @throws ProcedureNotFoundException
     */
    public function getStatusPercentageDistribution(CurrentProcedureService $currentProcedureService, AnnotatedStatementPdfService $annotatedStatementPdfService): APIResponse
    {
        $procedure = $currentProcedureService->getProcedureWithCertainty();
        $status = $annotatedStatementPdfService->getPercentageDistribution($procedure);
        $collection = $this->resourceService->makeCollection(
            [$status],
            PercentageDistributionTransformer::class,
            'AnnotatedStatementPdfPercentageDistribution'
        );

        return $this->renderResource($collection);
    }
}
