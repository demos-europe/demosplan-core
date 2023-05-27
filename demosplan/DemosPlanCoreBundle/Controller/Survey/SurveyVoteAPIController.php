<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Survey;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyHandler;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyService;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyVoteCreateHandler;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyVoteHandler;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyVoteService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SurveyVoteResourceType;
use demosplan\DemosPlanCoreBundle\Validator\SurveyVoteValidator;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SurveyVoteAPIController extends APIController
{
    /**
     * @DplanPermissions("area_survey")
     */
    #[Route(path: '/api/1.0/survey/{surveyId}/relationships/votes', methods: ['GET'], name: 'dplan_surveyvote_list', options: ['expose' => true])]
    public function listAction(
        SurveyService $surveyService,
        SurveyVoteService $surveyVoteService,
        string $surveyId
    ): APIResponse {
        try {
            $survey = $surveyService->findById($surveyId);
            $surveyVotes = $surveyVoteService->findByProcedure($survey->getProcedure());
            $collection = $this->resourceService->makeCollectionOfResources(
                $surveyVotes,
                SurveyVoteResourceType::getName()
            );

            return $this->renderResource($collection);
        } catch (Exception $e) {
            return $this->handleApiError($e);
        }
    }

    /**
     *
     * @DplanPermissions("area_survey_management")
     * @throws MessageBagException
     */
    #[Route(path: '/api/1.0/surveyVote/{surveyVoteId}', methods: ['PATCH'], name: 'dplan_surveyvote_update', options: ['expose' => true])]
    public function updateSurveyVoteAction(
        CurrentProcedureService $currentProcedureService,
        SurveyVoteService $surveyVoteService,
        string $surveyVoteId
    ): APIResponse {
        try {
            $surveyVote = $surveyVoteService->findById($surveyVoteId);
            /** @var ResourceObject $resourceObjectSurveyVote */
            $resourceObjectSurveyVote = $this->requestData->getFirst('SurveyVote');
            $procedureId = $currentProcedureService->getProcedureIdWithCertainty();
            $this->updateSurveyVoteValidation($surveyVote, $procedureId);

            $surveyVote->setTextReview($resourceObjectSurveyVote->get('textReview'));
            $surveyVoteService->updateObject($surveyVote);

            $this->updateSurveyVoteConfirmMessage($surveyVote);

            return $this->renderEmpty();
        } catch (Exception $e) {
            $this->messageBag->add('error', 'error.survey.votes.publication');

            return $this->handleApiError($e);
        }
    }

    /**
     *
     * @DplanPermissions("feature_surveyvote_may_vote")
     * @throws MessageBagException
     */
    #[Route(name: 'dplan_surveyvote_create', methods: 'POST', path: '/api/1.0/surveyVote', options: ['expose' => true])]
    public function createAction(
        SurveyVoteCreateHandler $surveyVoteCreateHandler,
        SurveyVoteHandler $surveyVoteHandler,
        SurveyVoteValidator $validator,
        SurveyHandler $surveyHandler,
        Request $request
    ): Response {
        if (!($this->requestData instanceof TopLevel)) {
            throw BadRequestException::normalizerFailed();
        }
        try {
            $validator->validateJson($request->getContent());
            $resourceObject = $this->requestData->getObjectToCreate();
            if (!$this->checkRequiredAgreements($resourceObject)) {
                return $this->handleApiError();
            }
            $surveyId = $surveyVoteCreateHandler->getRequestSurveyId($resourceObject);
            $surveyVote = $surveyVoteCreateHandler->getRequestSurveyVote(
                $resourceObject->get('isAgreed'),
                $resourceObject->get('text'),
                $surveyVoteCreateHandler->getRequestUserId($resourceObject),
                $surveyId
            );
            $surveyVoteHandler->updateObject($surveyVote);
            $survey = $surveyHandler->findById($surveyId);
            $this->messageBag->add('confirm', 'survey.comment.created');

            return $this->getCreateResponse($surveyVoteHandler, $survey);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->messageBag->add('error', 'error.generic');

            return $this->handleApiError($e);
        }
    }

    private function getCreateResponse(
        SurveyVoteHandler $surveyVoteHandler,
        Survey $survey
    ): Response {
        $response = $this->renderSuccess();
        $responseContent = Json::decodeToArray($response->getContent());
        $surveyVotesInfo = $surveyVoteHandler->getSurveyVotesInfo($survey, true);
        $responseContent['meta']['votes'] = $surveyVotesInfo;
        $response->setContent(Json::encode($responseContent));

        return $response;
    }

    /**
     * @throws MessageBagException
     */
    private function checkRequiredAgreements(ResourceObject $resourceObject): bool
    {
        $accepted = true;
        if (!$resourceObject->isPresent('r_confirm_locality') ||
            !$resourceObject->get('r_confirm_locality')) {
            $this->messageBag->add('warning', 'warning.local.participant.confirm');
            $accepted = false;
        }
        if (!$resourceObject->isPresent('r_gdpr_consent') ||
            !$resourceObject->get('r_gdpr_consent')) {
            $this->messageBag->add('warning', 'warning.gdpr.consent');
            $accepted = false;
        }
        if (!$resourceObject->isPresent('r_privacy') ||
            !$resourceObject->get('r_privacy')) {
            $this->messageBag->add('warning', 'warning.privacy.confirm');
            $accepted = false;
        }

        return $accepted;
    }

    /**
     * Determine and create confirm messages.
     *
     * @throws MessageBagException
     */
    protected function updateSurveyVoteConfirmMessage(SurveyVote $surveyVote): void
    {
        $message = 'survey.votes.publication.rejection';
        if (SurveyVote::PUBLICATION_APPROVED === $surveyVote->getTextReview()) {
            $message = 'survey.votes.publication.approval';
        }
        $this->messageBag->add('confirm', $message);
    }

    /**
     * @throws Exception
     */
    protected function updateSurveyVoteValidation(SurveyVote $surveyVote, $procedureId): void
    {
        // validate format
        if (!($this->requestData instanceof TopLevel)) {
            throw BadRequestException::normalizerFailed();
        }

        // validate that SurveyVote
        if ($procedureId !== $surveyVote->getSurvey()->getProcedure()->getId()) {
            throw new Exception('SurveyVote is not part of Survey or Procedure.');
        }
    }
}
