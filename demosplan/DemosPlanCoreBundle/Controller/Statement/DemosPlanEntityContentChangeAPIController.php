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
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeDisplayHandler;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService;
use demosplan\DemosPlanCoreBundle\Transformers\EntityContentChangeComparisonTransformer;
use demosplan\DemosPlanCoreBundle\Transformers\HistoryDayTransformer;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DemosPlanEntityContentChangeAPIController extends APIController
{
    // @improve T12984
    /**
     *
     * @DplanPermissions("feature_statement_content_changes_view")
     * This action provides all formatted diffs of all EntityContentChange objects of one specific change instance.
     * A change instance is a moment in time when an entity is changed. E.g. if person A changes Statement B at time C.
     * Then the combination of ABC is a change instance.
     */
    #[Route(path: '/api/1.0/statements/{procedureId}/entitycontentchange/{entityContentChangeId}', name: 'dplan_api_history_of_all_fields_of_specific_datetime', methods: ['GET'], options: ['expose' => true])]
    public function getEntityContentChangeAction(
        EntityContentChangeService $contentChangeService,
        string $entityContentChangeId
    ): APIResponse {
        // any entity content change object with the correct create time
        $contentChange = $contentChangeService->findByIdWithCertainty($entityContentChangeId);
        $changeInstances = $contentChangeService->findAllObjectsOfChangeInstance($contentChange);

        return $this->renderItem($changeInstances, EntityContentChangeComparisonTransformer::class);
    }

    // @improve T12984
    /**
     *
     * @DplanPermissions("feature_statement_fragment_content_changes_view")
     * @return APIResponse|JsonResponse
     */
    #[Route(path: '/api/1.0/statements/{procedureId}/statementfragment/{statementFragmentId}/history', name: 'dplan_api_statement_fragment_history', methods: ['GET'], options: ['expose' => true])]
    public function getStatementFragmentHistoryAction(
        CurrentProcedureService $currentProcedureService,
        EntityContentChangeDisplayHandler $displayHandler,
        StatementFragmentService $statementFragmentService,
        string $statementFragmentId,
        string $procedureId)
    {
        $statementFragment = $statementFragmentService->getStatementFragment($statementFragmentId);
        if (null === $statementFragment) {
            $this->messageBag->add('error', 'error.statementFragment.not.found');
            throw new EntityNotFoundException(sprintf('Statement Fragment not found %s', $statementFragmentId));
        }
        if ($currentProcedureService->getProcedureIdWithCertainty() !== $statementFragment->getProcedureId()) {
            throw new AccessDeniedException('ProcedureId of given StatementFragment is not equals to given procedureId');
        }

        $data = $displayHandler->getHistoryByEntityId($statementFragmentId, StatementFragment::class);

        return $this->renderCollection($data, HistoryDayTransformer::class);
    }

    /**
     *
     * This action provides all formatted diffs of all EntityContentChange objects of one specific change instance.
     * A change instance is a moment in time when an entity is changed. E.g. if person A changes Statement B at time C.
     * Then the combination of ABC is a change instance.
     * @DplanPermissions("feature_segment_content_changes_view")
     */
    #[Route(path: '/api/1.0/segments/{procedureId}/entitycontentchange/{entityContentChangeId}', name: 'dplan_api_segments_history_of_all_fields_of_specific_datetime', methods: ['GET'], options: ['expose' => true])]
    public function getSegmentContentChangeAction(
        EntityContentChangeService $contentChangeService,
        string $entityContentChangeId
    ): APIResponse {
        // any entity content change object with the correct create time
        $contentChange = $contentChangeService->findByIdWithCertainty($entityContentChangeId);
        $changeInstance = $contentChangeService->findAllObjectsOfChangeInstance($contentChange);

        return $this->renderItem($changeInstance, EntityContentChangeComparisonTransformer::class);
    }
}
