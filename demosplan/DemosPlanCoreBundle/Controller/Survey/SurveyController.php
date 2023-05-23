<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Survey;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyCreateHandler;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyHandler;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyNewHandler;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyUpdateHandler;
use demosplan\DemosPlanCoreBundle\Validator\SurveyValidator;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class SurveyController extends BaseController
{
    /**
     * @Route(
     *     name="dplan_survey_new",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/umfrage/neu")
     *
     * @DplanPermissions("area_survey_management")
     *
     * @throws MessageBagException
     * @throws Exception
     */
    public function newAction(
        string $procedureId,
        SurveyHandler $surveyHandler,
        SurveyNewHandler $surveyNewHandler,
        ProcedureHandler $procedureHandler,
        SurveyValidator $validator,
        TranslatorInterface $translator): Response
    {
        try {
            $validator->procedureExists($procedureId);
            $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);

            // only one survey per procedure is allowed
            if (null !== $procedure->getFirstSurvey()) {
                return $this->redirectToRoute(
                    'dplan_survey_edit',
                    ['procedureId' => $procedure->getId(), 'surveyId' => $procedure->getFirstSurvey()->getId()]
                );
            }

            $survey = $surveyNewHandler->getSurveyDefaults($procedure);
            $surveyStatuses = $surveyHandler->getSurveyStatusesArray($procedure);
            $procedureStartDate = $procedure->getPublicParticipationStartDate();

            return $this->renderTemplate(
                '@DemosPlanProcedure/DemosPlanProcedure/administration_survey_form.html.twig',
                [
                    'surveyStatuses'     => $surveyStatuses,
                    'procedure'          => $procedureId,
                    'procedureStartDate' => $procedureStartDate,
                    'survey'             => $survey,
                    'title'              => $translator->trans('survey.new'),
                ]
            );
        } catch (Exception $e) {
            $this->getLogger()->error($e);
            $this->getMessageBag()->add('error', 'error.generic');
            throw $e;
        }
    }

    /**
     * @Route(
     *     name="dplan_survey_create",
     *     methods="POST",
     *     path="/verfahren/{procedureId}/umfrage/create")
     *
     * @DplanPermissions("area_survey_management")
     *
     * @throws MessageBagException
     * @throws Exception
     */
    public function createAction(
        string $procedureId,
        SurveyHandler $surveyHandler,
        SurveyCreateHandler $surveyCreateHandler,
        Request $request
    ): Response {
        try {
            $surveyDataJson = $surveyHandler->getSurveyJsonData(
                $procedureId,
                $request->request->all())
            ;
            /** @var Survey $survey */
            $survey = $surveyCreateHandler->jsonToEntity($surveyDataJson);
            $surveyHandler->updateObject($survey);
            $this->getMessageBag()->add('confirm', 'survey.confirm.created');

            return $this->redirectToRoute(
                'dplan_survey_edit',
                ['procedureId' => $procedureId, 'surveyId' => $survey->getId()]
            );
        } catch (SurveyInputDataException $e) {
            $this->getMessageBag()->add('error', $e->getUserMsg());

            return $this->redirectToRoute(
                'dplan_survey_new',
                ['procedureId' => $procedureId]
            );
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.generic');
            $this->getLogger()->error($e->getMessage());
            throw $e;
        }
    }

    /**
     * @Route(
     *     name="dplan_survey_edit",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/umfrage/{surveyId}/edit")
     *
     * @DplanPermissions("area_survey_management")
     *
     * @throws MessageBagException
     * @throws Exception
     */
    public function editAction(
        string $procedureId,
        string $surveyId,
        SurveyHandler $surveyHandler,
        ProcedureHandler $procedureHandler,
        TranslatorInterface $translator
    ): Response {
        try {
            $survey = $surveyHandler->getProcedureSurvey($procedureId, $surveyId);
            $procedure = $procedureHandler->getProcedureWithCertainty($procedureId);
            $surveyStatuses = $surveyHandler->getSurveyStatusesArray($procedure);
            $procedureStartDate = $procedure->getPublicParticipationStartDate();

            return $this->renderTemplate(
                '@DemosPlanProcedure/DemosPlanProcedure/administration_survey_form.html.twig',
                [
                    'survey'             => $survey,
                    'surveyStatuses'     => $surveyStatuses,
                    'procedure'          => $procedureId,
                    'surveyId'           => $surveyId,
                    'procedureStartDate' => $procedureStartDate,
                    'title'              => $translator->trans('survey.edit'),
                ]
            );
        } catch (Exception $e) {
            $this->getLogger()->error($e);
            $this->getMessageBag()->add('error', 'error.generic');
            throw $e;
        }
    }

    /**
     * @Route(
     *     name="dplan_survey_update",
     *     methods="POST",
     *     path="/verfahren/{procedureId}/umfrage/{surveyId}/update")
     *
     * @DplanPermissions("area_survey_management")
     *
     * @throws MessageBagException
     * @throws Exception
     */
    public function updateAction(
        string $procedureId,
        string $surveyId,
        SurveyUpdateHandler $surveyUpdateHandler,
        SurveyHandler $surveyHandler,
        Request $request
    ): Response {
        try {
            $surveyDataJson = $surveyHandler->getSurveyJsonData(
                $procedureId,
                $request->request->all(),
                $surveyId
            );
            /** @var Survey $survey */
            $survey = $surveyUpdateHandler->jsonToEntity($surveyDataJson);
            $surveyHandler->updateObject($survey);
            $this->getMessageBag()->add('confirm', 'survey.confirm.updated');
        } catch (SurveyInputDataException $e) {
            $this->getMessageBag()->add('error', $e->getUserMsg());
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.generic');
            $this->getLogger()->error($e->getMessage());
            throw $e;
        }

        return $this->redirectToRoute(
            'dplan_survey_edit',
            ['procedureId' => $procedureId, 'surveyId' => $surveyId]
        );
    }

    /**
     * @Route(
     *     name="dplan_survey_show",
     *     methods="GET",
     *     path="/verfahren/{procedureId}/umfrage/{surveyId}")
     *
     * @DplanPermissions("area_survey")
     *
     * @throws MessageBagException
     * @throws Exception
     */
    public function showAction(
        string $procedureId,
        string $surveyId,
        SurveyHandler $surveyHandler
    ): Response {
        try {
            $survey = $surveyHandler->getProcedureSurvey($procedureId, $surveyId);

            return $this->renderTemplate(
                '@DemosPlanProcedure/DemosPlanProcedure/administration_list_survey_comments.html.twig',
                [
                    'survey'    => $survey,
                    'procedure' => $procedureId,
                    'surveyId'  => $surveyId,
                ]
            );
        } catch (Exception $e) {
            $this->getLogger()->error($e);
            $this->getMessageBag()->add('error', 'error.generic');
            throw $e;
        }
    }
}
