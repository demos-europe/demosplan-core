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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadySegmentedException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Validator\SegmentableStatementValidator;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DraftsInfoController extends BaseController
{
    /**
     * Assigns the Statement to the user in session (to avoid concurrency problems) and
     * redirects to dplan_drafts_list_edit.
     *
     * @throws StatementNotFoundException
     *
     * @DplanPermissions("area_statement_segmentation")
     */
    // TODO: receiving the statement ID here may result in concurrency problems
    // because multiple users may be shown the same butten (with the same ID) and
    // it is unknown what happens if they both use it but it will be nothing good.
    // Instead of receiving the statement ID the BE should chose a statement by
    // itself in this route.
    #[Route(name: 'dplan_drafts_list_claim', methods: 'POST', path: '/verfahren/{procedureId}/statements/{statementId}/drafts-list', options: ['expose' => true])]
    public function startSegmentationAction(
        CurrentUserService $currentUser,
        StatementService $statementService,
        string $statementId,
        string $procedureId
    ): RedirectResponse {
        $statement = $statementService->getStatement($statementId);
        if (null === $statement) {
            throw StatementNotFoundException::createFromId($statementId);
        }
        $statement->setAssignee($currentUser->getUser());
        $statementService->updateStatementFromObject($statement);

        return $this->redirectToRoute(
            'dplan_drafts_list_edit',
            [
                'statementId' => $statementId,
                'procedureId' => $procedureId,
            ]
        );
    }

    /**
     * Gets the Twig Template to call the endpoint loading the Statement's Text with
     * the segmentation.
     *
     * @throws Exception
     *
     * @DplanPermissions("area_statement_segmentation")
     */
    #[Route(name: 'dplan_drafts_list_edit', methods: 'GET', path: '/verfahren/{procedureId}/statement/{statementId}/drafts-list', options: ['expose' => true])]
    public function editAction(
        string $procedureId,
        string $statementId,
        SegmentableStatementValidator $segmentableStatementValidator,
        StatementHandler $statementHandler
    ): Response {
        try {
            $segmentableStatementValidator->validate($statementId);
            /** @var Statement $statement */
            $statement = $statementHandler->getStatement($statementId);
            $procedureName = $statement->getProcedure()->getName();

            return $this->renderTemplate(
                '@DemosPlanCore/DemosPlanProcedure/administration_split_statement.html.twig',
                [
                    'statementId'       => $statementId,
                    'statementExternId' => $statement->getExternId(),
                    'procedure'         => $procedureId,
                    'procedureName'     => $procedureName,
                    'title'             => 'statement.do.fragment',
                ]
            );
        } catch (StatementNotFoundException $e) {
            $this->getMessageBag()->add('error', 'error.statement.not.found');
            throw $e;
        } catch (StatementAlreadySegmentedException $e) {
            $this->messageBag->add('error', 'error.statement.already.segmented');
            throw $e;
        } catch (LockedByAssignmentException $e) {
            $this->messageBag->add('error', 'error.statement.not.assigned');
            throw $e;
        }
    }
}
