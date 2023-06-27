<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureStatisticsService;
use demosplan\DemosPlanCoreBundle\Transformers\PercentageDistributionTransformer;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProcedureStatisticsRpcController extends APIController
{
    /**
     * @DplanPermissions("area_statement_segmentation")
     *
     * @return APIResponse|Response
     */
    #[Route(path: '/rpc/1.0/ProcedureStatistics/get/{procedureId}', name: 'dplan_rpc_procedure_segmentation_statistics_segmentations_get', methods: ['GET'], options: ['expose' => true])]
    public function segmentationsGetAction(
        ProcedureStatisticsService $procedureStatisticsService,
        string $procedureId
    ): Response {
        try {
            $distribution = $procedureStatisticsService->getSegmentedStatementsDistribution($procedureId);

            return $this->renderItem($distribution, PercentageDistributionTransformer::class);
        } catch (Exception $e) {
            $this->logger->warning('Could not retrieve procedure statistics.', [$e]);

            return new Response(null, Response::HTTP_BAD_REQUEST, []);
        }
    }
}
