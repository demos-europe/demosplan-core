<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\APIController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureHandler;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use demosplan\DemosPlanStatementBundle\Exception\InvalidStatusTransitionException;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DemosPlanAnnotatedStatementPdfController extends APIController
{
    /**
     * @Route(
     *     name="dplan_annotated_statement_pdf_review",
     *     methods={"GET"},
     *     path="/verfahren/{procedureId}/annotatedStatementPdf/{documentId}/review",
     *     options={"expose": true})
     *
     * @DplanPermissions("feature_import_statement_pdf")
     *
     * @throws InvalidStatusTransitionException
     * @throws MessageBagException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function reviewAction(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        GlobalConfigInterface $globalConfig,
        string $procedureId,
        string $documentId
    ): Response {
        $annotatedStatementPdf = $annotatedStatementPdfHandler->findOneById($documentId);
        if (!$annotatedStatementPdfHandler->validateBoxReview($annotatedStatementPdf)) {
            return new RedirectResponse(
                $this->generateUrl(
                    'DemosPlan_procedure_dashboard',
                    ['procedure' => $procedureId]
                )
            );
        }

        // Access these action while the AnnotatedStatementPdf is already
        // in review by the current user, is a valid case.
        // But setting the status form BOX_REVIEW to BOX_REVIEW is not allowed.
        if (AnnotatedStatementPdf::BOX_REVIEW !== $annotatedStatementPdf->getStatus()) {
            $annotatedStatementPdfHandler->setBoxReviewStatus($annotatedStatementPdf);
        }

        $templateVars = [
            'documentId'       => $documentId,
            'aiPipelineLabels' => $globalConfig->getAiPipelineLabels(),
        ];

        return $this->renderTemplate(
            '@DemosPlanProcedure/DemosPlanProcedure/administration_annotate.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => 'statements.uploaded.recheck',
            ]
        );
    }

    /**
     * @Route(
     *     name="dplan_next_annotated_statement_pdf_to_review",
     *     methods={"GET"},
     *     path="/verfahren/{procedureId}/next-annotated-statement-to-review/{documentId}",
     *     options={"expose": true})
     *
     * @DplanPermissions("feature_import_statement_pdf")
     *
     * @throws Exception
     */
    public function nextAnnotatedStatementPdfToReviewAction(
        ProcedureHandler $procedureHandler,
        string $procedureId
    ): Response {
        $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);

        $result = [
            'documentId' => $procedure->getNextAnnotatedStatementPdfToReview(),
        ];

        return APIResponse::create($result, 200);
    }

    /**
     * @Route(
     *     name="dplan_convert_annotated_pdf_to_statement",
     *     methods={"GET"},
     *     path="/verfahren/{procedureId}/annotatedStatementPdf/{documentId}/umwandeln",
     *     options={"expose": true})
     *
     * @DplanPermissions("feature_import_statement_pdf")
     *
     * @throws Exception
     */
    public function convertToStatementAction(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        GlobalConfigInterface $globalConfig,
        PermissionsInterface $permissions,
        ProcedureService $procedureService,
        CurrentProcedureService $currentProcedureService,
        StatementService $statementService,
        string $procedureId,
        string $documentId): Response
    {
        $annotatedStatementPdf = $annotatedStatementPdfHandler->findOneById($documentId);
        if (!$this->validateTextReview($annotatedStatementPdf)) {
            return new RedirectResponse(
                $this->generateUrl(
                    'DemosPlan_procedure_dashboard',
                    ['procedure' => $procedureId]
                )
            );
        }
        $annotatedStatementPdfHandler->setTextReviewStatus($annotatedStatementPdf);

        $templateVars = [
            'documentId'            => $documentId,
            'newestInternalId'      => $statementService->getNewestInternId($procedureId),
            'usedInternIds'         => $statementService->getInternIdsFromProcedure($procedureId),
            'aiPipelineLabels'      => $globalConfig->getAiPipelineLabels(),
            'submitter'             => $annotatedStatementPdf->getSubmitterJson(),
            'currentProcedurePhase' => $currentProcedureService->getProcedureWithCertainty()->getPhase(),
        ];
        if ($permissions->hasPermission('feature_statements_tag')) {
            $templateVars['availableTopics'] = $procedureService->getTopics($procedureId);
        }

        return $this->renderTemplate(
            '@DemosPlanProcedure/DemosPlanProcedure/administration_convert_annotated_pdf.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedureId,
                'title'        => 'statements.uploaded.confirm',
            ]
        );
    }

    /**
     * @throws MessageBagException
     */
    private function validateTextReview(
        AnnotatedStatementPdf $annotatedStatementPdf
    ): bool {
        if (AnnotatedStatementPdf::TEXT_REVIEW === $annotatedStatementPdf->getStatus()) {
            $this->getMessageBag()->add(
                'error',
                'error.annotated.statement.text.already.being.reviewed',
                ['user' => $annotatedStatementPdf->getReviewer()->getName()]
            );

            return false;
        }
        if (AnnotatedStatementPdf::READY_TO_CONVERT !== $annotatedStatementPdf->getStatus()) {
            $this->getMessageBag()->add(
                'error',
                'error.annotated.statement.not.ready.to.confirm',
                ['user' => $annotatedStatementPdf->getReviewer()->getName()]
            );

            return false;
        }

        return true;
    }
}
