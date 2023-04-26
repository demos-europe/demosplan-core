<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Survey;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use demosplan\DemosPlanCoreBundle\Entity\Survey\SurveyVote;
use demosplan\DemosPlanCoreBundle\Validator\SurveyVoteValidator;
use demosplan\DemosPlanUserBundle\Logic\UserHandler;
use Exception;
use JsonSchema\Exception\InvalidArgumentException;

class SurveyVoteCreateHandler
{
    /** @var SurveyVoteValidator */
    private $surveyVoteValidator;

    /** @var UserHandler */
    private $userHandler;

    /** @var SurveyHandler */
    private $surveyHandler;

    public function __construct(SurveyVoteValidator $surveyVoteValidator, UserHandler $userHandler, SurveyHandler $surveyHandler)
    {
        $this->surveyVoteValidator = $surveyVoteValidator;
        $this->userHandler = $userHandler;
        $this->surveyHandler = $surveyHandler;
    }

    public function getRequestUserId(ResourceObject $resourceObject): string
    {
        $relationships = $resourceObject->get('relationships');

        return $relationships['user']['data']['id'];
    }

    public function getRequestSurveyId(ResourceObject $resourceObject): string
    {
        $relationships = $resourceObject->get('relationships');

        return $relationships['survey']['data']['id'];
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getRequestSurveyVote(
        string $isAgreed,
        string $text,
        string $userId,
        string $surveyId
    ): SurveyVote {
        $user = $this->userHandler->getSingleUser($userId);
        if (null === $user) {
            throw new InvalidArgumentException('No User found for id : '.$userId);
        }

        $survey = $this->surveyHandler->findById($surveyId);
        if (null === $survey) {
            throw new InvalidArgumentException('No Survey found for id : '.$surveyId);
        }
        $this->surveyVoteValidator->userCanVote($survey, $user);

        return new SurveyVote($isAgreed, $text, $survey, $user);
    }
}
