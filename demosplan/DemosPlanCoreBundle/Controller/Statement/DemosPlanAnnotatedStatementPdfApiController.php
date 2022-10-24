<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\APIController;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PrefilledResourceTypeProvider;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\ApiClientInterface;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ApiLogger;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AnnotatedStatementPdfResourceType;
use demosplan\DemosPlanCoreBundle\Response\APIResponse;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfHandler;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\AnnotatedStatementPdfPageToEntityConverter;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiErrorManagement\PiBoxRecognitionErrorManager;
use demosplan\DemosPlanStatementBundle\Logic\AnnotatedStatementPdf\PiErrorManagement\PiTextRecognitionErrorManager;
use demosplan\DemosPlanStatementBundle\Repository\AnnotatedStatementPdf\AnnotatedStatementPdfRepository;

class DemosPlanAnnotatedStatementPdfApiController extends APIController
{
    /** @var ApiClientInterface */
    private $apiClient;

    /** @var AnnotatedStatementPdfPageToEntityConverter */
    private $jsonToEntityConverter;

    /** @var AnnotatedStatementPdfHandler */
    private $annotatedStatementPdfHandler;

    public function __construct(
        ApiClientInterface $apiClient,
        ApiLogger $apiLogger,
        AnnotatedStatementPdfPageToEntityConverter $jsonToEntityConverter,
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        PrefilledResourceTypeProvider $resourceTypeProvider,
        TranslatorInterface $translator
    ) {
        parent::__construct($apiLogger, $resourceTypeProvider, $translator);
        $this->apiClient = $apiClient;
        $this->jsonToEntityConverter = $jsonToEntityConverter;
        $this->annotatedStatementPdfHandler = $annotatedStatementPdfHandler;
    }

    /**
     * Action called by PI with a url to retrieve the pages (with their corresponding
     * sections/boxes) for the AnnotatedStatementPdf.
     *
     * The url will be called to get the info, generate the AnnotatedStatementPdfPage entities
     * and update AnnotatedStatementPdf.
     *
     * @Route(
     *     name="dplan_ai_api_annotation_statement_pdf_boxes_proposal",
     *     methods={"POST"},
     *     path="/api/ai/annotated-statement-pdf/boxes-proposal/{annotatedStatementPdfId}")
     * )
     *
     * @DplanPermissions("feature_ai_create_annotated_statement_pdf_pages")
     *
     * @return APIResponse
     */
    public function boxesProposalAction(Request $request, string $annotatedStatementPdfId): Response
    {
        try {
            $this->logger->info('Try to update AnnotatedStatementPdf on boxes proposal from PI for AnnotatedStatementPdf: '.$annotatedStatementPdfId);

            $this->updateAnnotatedStatementPdf(
                $request,
                $annotatedStatementPdfId,
                AnnotatedStatementPdf::READY_TO_REVIEW,
                true
            );

            return $this->createEmptyResponse();
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     * Action called by PI when error while doing the pdf boxes recognition.
     *
     * @Route(
     *     name="dplan_ai_api_annotation_statement_pdf_boxes_proposal_error",
     *     methods={"POST"},
     *     path="/api/ai/annotated-statement-pdf/boxes-proposal-error/{annotatedStatementPdfId}")
     * )
     *
     * @DplanPermissions("feature_ai_create_annotated_statement_pdf_pages")
     *
     * @return APIResponse
     */
    public function boxesProposalErrorAction(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        PiBoxRecognitionErrorManager $errorManager,
        Request $request,
        string $annotatedStatementPdfId
    ): Response {
        try {
            $piErrorInfo = $this->getPiResourceInfo($request);
            $annotatedStatementPdf = $annotatedStatementPdfHandler->findOneById(
                $annotatedStatementPdfId
            );
            $errorManager->managePiError(
                $annotatedStatementPdf,
                $annotatedStatementPdfId,
                $piErrorInfo
            );

            return $this->createEmptyResponse();
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     * Action called by PI with a url to retrieve the Statement Text Proposal for the
     * AnnotatedStatementPdf.
     *
     * The url will be called to get the text and update the entity accordingly.
     *
     * @Route(
     *     name="dplan_ai_api_annotation_statement_pdf_text_proposal",
     *     methods={"POST"},
     *     path="/api/ai/annotated-statement-pdf/text-proposal/{annotatedStatementPdfId}")
     * )
     *
     * @DplanPermissions("feature_ai_create_annotated_statement_pdf_pages")
     *
     * @return APIResponse
     */
    public function textProposalAction(Request $request, string $annotatedStatementPdfId): Response
    {
        try {
            $this->updateAnnotatedStatementPdf(
                $request,
                $annotatedStatementPdfId,
                AnnotatedStatementPdf::READY_TO_CONVERT,
                false
            );

            return $this->createEmptyResponse();
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     * Action called by PI when error while doing the text recognition from a pdf.
     *
     * @Route(
     *     name="dplan_ai_api_annotation_statement_pdf_text_proposal_error",
     *     methods={"POST"},
     *     path="/api/ai/annotated-statement-pdf/text-proposal-error/{annotatedStatementPdfId}")
     * )
     *
     * @DplanPermissions("feature_ai_create_annotated_statement_pdf_pages")
     *
     * @return APIResponse
     */
    public function textProposalErrorAction(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        PiTextRecognitionErrorManager $errorManager,
        Request $request,
        string $annotatedStatementPdfId
    ): Response {
        try {
            $piErrorInfo = $this->getPiResourceInfo($request);
            $annotatedStatementPdf = $annotatedStatementPdfHandler->findOneById(
                $annotatedStatementPdfId
            );
            $errorManager->managePiError(
                $annotatedStatementPdf,
                $annotatedStatementPdfId,
                $piErrorInfo
            );

            return $this->createEmptyResponse();
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     * @throws JsonException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function updateAnnotatedStatementPdf(
        Request $request,
        string $annotatedStatementPdfId,
        string $status,
        bool $updatePages
    ): void {
        $piResourceInfo = $this->getPiResourceInfo($request);
        if ('' === $piResourceInfo) {
            throw new InvalidArgumentException('No resource info received from PI.');
        }
        $annotatedStatementPdf = $this
            ->annotatedStatementPdfHandler
            ->findOneById($annotatedStatementPdfId);

        $annotatedStatementPdf = $this
            ->jsonToEntityConverter
            ->convert($annotatedStatementPdf, $piResourceInfo, $updatePages);

        $annotatedStatementPdf->setStatus($status);
        $this->annotatedStatementPdfHandler->updateObjects([$annotatedStatementPdf]);
    }

    /**
     * @Route(
     *     name="dplan_ai_api_get_annotation_statement_pdf",
     *     methods={"GET"},
     *     path="/api/ai/annotated-statement-pdf/{annotatedStatementPdfId}")
     *
     * @DplanPermissions("feature_ai_create_annotated_statement_pdf_pages")
     *
     * @return APIResponse
     *
     * @throws Exception
     */
    public function getAnnotatedStatementPdfAction(
        CurrentProcedureService $currentProcedure,
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        string $annotatedStatementPdfId): Response
    {
        $annotatedStatement = $annotatedStatementPdfHandler->findOneById($annotatedStatementPdfId);
        $currentProcedure->setProcedure($annotatedStatement->getProcedure());

        $resource = $this->resourceService->makeItemOfResource(
            $annotatedStatement,
            AnnotatedStatementPdfResourceType::getName()
        );

        return $this->renderResource($resource);
    }

    /**
     * @Route(
     *     name="dplan_next_annotated_pdf",
     *     methods={"GET"},
     *     path="/verfahren/{procedureId}/nextAnnotatedStatementPdf/{documentId}",
     *     options={"expose": true})
     *
     * @DplanPermissions("feature_import_statement_pdf")
     *
     * @throws Exception
     */
    public function nextAnnotatedStatementPdfAction(
        AnnotatedStatementPdfRepository $annotatedStatementPdfRepository,
        string $procedureId
    ): Response {

        $result = [
            'documentId' => $annotatedStatementPdfRepository->getNextAnnotatedStatementPdfToReview($procedureId),
        ];

        return APIResponse::create($result, 200);
    }

    private function getPiResourceInfo(Request $request): string
    {
        $requestBody = Json::decodeToArray($request->getContent());
        $piResourceUrl = isset($requestBody['result_url']) ? $requestBody['result_url'] : '';
        $this->logger->info(">$piResourceUrl<");
        if ('' === $piResourceUrl || null === $piResourceUrl) {
            return '';
        }
        $options = ['http_errors' => false];

        return $this->apiClient->request($piResourceUrl, $options, ApiClientInterface::GET);
    }

    /**
     * @Route(
     *     name="dplan_annotated_statement_pdf_pause_box_review",
     *     methods={"POST"},
     *     path="/verfahren/{procedureId}/annotatedStatementPdf/{documentId}/box-review/pause",
     *     options={"expose": true})
     *
     * @DplanPermissions("feature_import_statement_pdf")
     */
    public function pauseBoxReviewAction(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        string $documentId
    ): Response {
        try {
            $annotatedStatementPdf = $annotatedStatementPdfHandler->findOneById($documentId);
            if (AnnotatedStatementPdf::BOX_REVIEW !== $annotatedStatementPdf->getStatus()) {
                return $this->createInvalidStatusTransitionResponse();
            }
            $annotatedStatementPdfHandler->pauseBoxReviewStatus($annotatedStatementPdf);

            return $this->createResponse([], 200);
        } catch (Exception $e) {
            return $this->handleApiError();
        }
    }

    /**
     * @Route(
     *     name="dplan_annotated_statement_pdf_pause_text_review",
     *     methods={"POST"},
     *     path="/verfahren/{procedureId}/annotatedStatementPdf/{documentId}/text-review/pause",
     *     options={"expose": true})
     *
     * @DplanPermissions("feature_import_statement_pdf")
     */
    public function pauseTextReviewAction(
        AnnotatedStatementPdfHandler $annotatedStatementPdfHandler,
        string $documentId
    ): Response {
        try {
            $annotatedStatementPdf = $annotatedStatementPdfHandler->findOneById($documentId);
            if (AnnotatedStatementPdf::TEXT_REVIEW !== $annotatedStatementPdf->getStatus()) {
                return $this->createInvalidStatusTransitionResponse();
            }
            $annotatedStatementPdfHandler->pauseTextReviewStatus($annotatedStatementPdf);

            return $this->createResponse([], 200);
        } catch (Exception $e) {
            return $this->handleApiError();
        }
    }

    private function createInvalidStatusTransitionResponse(): JsonResponse
    {
        $data = [
            'errors' => [
                [
                    'status' => 422,
                ],
            ],
        ];

        return new JsonResponse($data);
    }
}
