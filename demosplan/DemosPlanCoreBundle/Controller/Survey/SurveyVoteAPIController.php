<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Survey;

use DemosEurope\DemosplanAddon\Controller\APIController;
use DemosEurope\DemosplanAddon\Response\APIResponse;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use use DemosEurope\DemosplanAddon\Logic\ApiRequest\TopLevel;
use demosplan\DemosPlanCoreBundle\ResourceTypes\SurveyVoteResourceType;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanSurveyBundle\Logic\SurveyHandler;
use demosplan\DemosPlanSurveyBundle\Logic\SurveyService;
use demosplan\DemosPlanSurveyBundle\Logic\SurveyVoteCreateHandler;
use demosplan\DemosPlanSurveyBundle\Logic\SurveyVoteHandler;
use demosplan\DemosPlanSurveyBundle\Logic\SurveyVoteService;
use demosplan\DemosPlanSurveyBundle\Validator\SurveyVoteValidator;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SurveyVoteAPIController extends APIController
{
    /**
     * @DplanPermissions("area_survey")
     * @Route(path="/api/1.0/survey/{surveyId}/relationships/votes",
     *        methods={"GET"},
     *        name="dplan_surveyvote_list",
     *        options={"expose": true})
     */
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
     * @Route(path="/api/1.0/surveyVote/{surveyVoteId}",
     *        methods={"PATCH"},
     *        name="dplan_surveyvote_update",
     *        options={"expose": true})
     * @DplanPermissions("area_survey_management")
     *
     * @throws MessageBagException
     */
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
            $this->getMessageBag()->add('error', 'error.survey.votes.publication');

            return $this->handleApiError($e);
        }
    }

    /**
     * @Route(
     *     name="dplan_surveyvote_create",
     *     methods="POST",
     *     path="/api/1.0/surveyVote",
     *     options={"expose": true})
     * @DplanPermissions("feature_surveyvote_may_vote")
     *
     * @throws MessageBagException
     */
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
            $this->getMessageBag()->add('confirm', 'survey.comment.created');

            return $this->getCreateResponse($surveyVoteHandler, $survey);
        } catch (Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->getMessageBag()->add('error', 'error.generic');

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
            $this->getMessageBag()->add('warning', 'warning.local.participant.confirm');
            $accepted = false;
        }
        if (!$resourceObject->isPresent('r_gdpr_consent') ||
            !$resourceObject->get('r_gdpr_consent')) {
            $this->getMessageBag()->add('warning', 'warning.gdpr.consent');
            $accepted = false;
        }
        if (!$resourceObject->isPresent('r_privacy') ||
            !$resourceObject->get('r_privacy')) {
            $this->getMessageBag()->add('warning', 'warning.privacy.confirm');
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
        $this->getMessageBag()->add('confirm', $message);
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
