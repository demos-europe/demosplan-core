<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Segment;

use DemosEurope\DemosplanAddon\Contracts\Events\AfterSegmentationEventInterface;
use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\Statement\AfterSegmentationEvent;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadySegmentedException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\DraftsInfoHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces\SegmentHandlerInterface;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces\SegmentTransformerInterface;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\SegmentTransformerPass;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\StatementToDraftsInfoTransformer;
use Doctrine\ORM\Query\QueryException;
use Exception;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DraftsInfoApiController extends APIController
{
    /**
     * Gets the Statement's text with the segmented info.
     *
     * @throws LockedByAssignmentException
     * @throws MessageBagException
     * @throws StatementAlreadySegmentedException
     * @throws StatementNotFoundException
     *
     * @DplanPermissions("area_statement_segmentation")
     */
    #[Route(name: 'dplan_drafts_list_edit_ajax', methods: 'GET', path: '/_ajax/verfahren/{procedureId}/statements/{statementId}/drafts-list', options: ['expose' => true])]
    public function editAction(
        StatementToDraftsInfoTransformer $transformer,
        string $procedureId,
        string $statementId
    ): Response {
        try {
            $draftInfo = $transformer->transform($statementId);
            $jsonResponse = new JsonResponse();
            $jsonResponse->setContent($draftInfo);

            return $jsonResponse;
        } catch (StatementNotFoundException $e) {
            $this->messageBag->add('error', 'error.statement.not.found');
            throw $e;
        } catch (StatementAlreadySegmentedException $e) {
            $this->messageBag->add('error', 'error.statement.already.segmented');
            throw $e;
        } catch (LockedByAssignmentException $e) {
            $this->messageBag->add('error', 'statement.not.claimed.by_current_user');
            throw $e;
        }
    }

    /**
     * Saves a Statement's draft segment (text + tags).
     *
     * @throws LockedByAssignmentException
     * @throws MessageBagException
     * @throws StatementAlreadySegmentedException
     * @throws StatementNotFoundException
     *
     * @DplanPermissions("area_statement_segmentation")
     */
    #[Route(name: 'dplan_drafts_list_save', methods: 'PATCH', path: '/_ajax/verfahren/{procedureId}/drafts-list/save/{statementId}', options: ['expose' => true])]
    public function saveAction(
        DraftsInfoHandler $draftsInfoHandler,
        Request $request,
        string $procedureId
    ): JsonResponse {
        try {
            $data = $request->getContent();
            $draftsInfoHandler->save($data);
            $this->messageBag->add('confirm', 'confirm.saved');

            return new JsonResponse();
        } catch (StatementNotFoundException $e) {
            $this->messageBag->add('error', 'error.statement.not.found');
            throw $e;
        } catch (StatementAlreadySegmentedException $e) {
            $this->messageBag->add('error', 'error.statement.already.segmented');
            throw $e;
        } catch (LockedByAssignmentException $e) {
            $this->messageBag->add('error', 'statement.not.claimed.by_current_user');
            throw $e;
        }
    }

    /**
     * Confirms the Statement's drafts info so they are converted to Segment entities.
     *
     * @throws LockedByAssignmentException
     * @throws MessageBagException
     * @throws QueryException
     * @throws StatementAlreadySegmentedException
     * @throws StatementNotFoundException
     * @throws Exception
     *
     * @DplanPermissions("area_statement_segmentation")
     */
    #[Route(name: 'dplan_drafts_list_confirm', methods: 'POST', path: '/verfahren/{procedureId}/drafts-list/confirm', options: ['expose' => true])]
    public function confirmDraftsAction(
        CurrentUserService $currentUserProvider,
        DraftsInfoHandler $draftsInfoHandler,
        EventDispatcherInterface $eventDispatcher,
        Request $request,
        SegmentHandlerInterface $segmentHandler,
        SegmentTransformerPass $transformer,
        StatementHandler $statementHandler,
        string $procedureId
    ): JsonResponse {
        try {
            $data = $request->getContent();
            // save the input JSON into the target statement (referenced in the JSON)
            $statementId = $draftsInfoHandler->save($data);
            // extract and create the segments from the input JSON
            /** @var array<int, Segment> $segments */
            $segments = $transformer->transform(
                $data,
                SegmentTransformerInterface::DRAFTS_INFO
            );

            if (0 === count($segments)) {
                $this->messageBag->add('error', 'statement.has.no.segments');

                return $this->handleApiError();
            }

            // persist the segments
            $segmentHandler->addSegments($segments);

            // request additional statement processing (asynchronous)
            $eventDispatcher->dispatch(new AfterSegmentationEvent($statementHandler->getStatementWithCertainty($statementId)), AfterSegmentationEventInterface::class);

            $currentUser = $currentUserProvider->getUser();

            // respond with the ID of the next statement that can be segmented by the current user
            return $this->confirmDraftsJsonResponse(
                $currentUser,
                $draftsInfoHandler,
                $statementHandler,
                $data
            );
        } catch (StatementNotFoundException $e) {
            $this->messageBag->add('error', 'error.statement.not.found');
            throw $e;
        } catch (StatementAlreadySegmentedException $e) {
            $this->messageBag->add('error', 'error.statement.already.segmented');
            throw $e;
        } catch (LockedByAssignmentException $e) {
            $this->messageBag->add('error', 'statement.not.claimed.by_current_user');
            throw $e;
        }
    }

    /**
     * Returns the next segmentable statement id if there is any left or empty
     * string otherwise.
     *
     * @throws MessageBagException
     * @throws QueryException
     */
    private function confirmDraftsJsonResponse(
        User $user,
        DraftsInfoHandler $draftsInfoHandler,
        StatementHandler $statementHandler,
        string $data
    ): JsonResponse {
        $responseData = [];
        $draftsInfoArray = Json::decodeToArray($data);
        $procedureId = $draftsInfoHandler->extractProcedureId($draftsInfoArray);
        $nextStatement = $statementHandler->getSegmentableStatement(
            $procedureId,
            $user
        );
        $nextStatementId = null === $nextStatement ? '' : $nextStatement->getId();
        $jsonResponse = new JsonResponse();
        $responseData['data'] = ['nextStatementId' => $nextStatementId];
        $jsonResponse->setData($responseData);
        $this->messageBag->add('confirm', 'confirm.segmented.statement');

        return $jsonResponse;
    }
}
