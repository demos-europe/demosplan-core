<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeDisplayHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentService;
use demosplan\DemosPlanCoreBundle\Transformers\HistoryDayTransformer;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use Exception;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class SegmentHistoryAPIController extends APIController
{
    /**
     * @DplanPermissions("feature_segment_content_changes_view")
     */
    #[Route(path: '/api/1.0/SegmentHistory/{segmentId}', methods: ['GET'], name: 'dplan_api_segment_history_get', options: ['expose' => true])]
    public function getAction(
        CurrentProcedureService $currentProcedureService,
        EntityContentChangeDisplayHandler $displayHandler,
        SegmentService $segmentService,
        string $segmentId): APIResponse
    {
        try {
            $segment = $segmentService->findByIdWithCertainty($segmentId);

            $procedureId = $currentProcedureService->getProcedureIdWithCertainty();
            if ($procedureId !== $segment->getProcedureId()) {
                // otherwise user can access to any statement history by url modification
                throw new AccessDeniedException();
            }

            $data = $displayHandler->getHistoryByEntityId(
                $segmentId,
                Segment::class
            );

            return $this->renderCollection($data, HistoryDayTransformer::class);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }
}
