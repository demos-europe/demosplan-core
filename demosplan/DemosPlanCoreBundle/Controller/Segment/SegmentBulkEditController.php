<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SegmentBulkEditController extends BaseController
{
    /**
     * @Route(
     *     name="dplan_segment_bulk_edit_form",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/abschnitte/bulk-edit",
     *     options={"expose": true}
     * )
     * @DplanPermissions("feature_segments_bulk_edit")
     *
     * @throws Exception
     */
    public function showFormAction(string $procedureId): Response
    {
        return $this->renderTemplate('@DemosPlanProcedure/DemosPlanProcedure/administration_segments_bulk_edit.html.twig', [
            'procedure' => $procedureId,
            'title'     => 'segments.bulk.edit',
        ]);
    }
}
