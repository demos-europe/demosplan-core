<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Validator;

use DateTime;
use DemosEurope\DemosplanAddon\Validator\JsonSchemaValidator;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyVoteHandler;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;

class SurveyVoteValidator
{
    public function __construct(private readonly JsonSchemaValidator $jsonSchemaValidator, private readonly SurveyVoteHandler $surveyVoteHandler, private readonly string $schemaFilePath)
    {
    }

    /**
     * @throws InvalidSchemaException
     */
    public function validateJson(string $json): void
    {
        $this->jsonSchemaValidator->validate($json, $this->schemaFilePath);
    }

    /**
     * @throws Exception
     * @throws InvalidSchemaException
     */
    public function surveyInVotingPeriod(Survey $survey): void
    {
        if (Survey::STATUS_PARTICIPATION !== $survey->getStatus()
            || $survey->getEndDate() < new DateTime('today')
            || $survey->getStartDate() > new DateTime()
        ) {
            $errorMsg = 'Survey#'.$survey->getId().' not open for voting';
            throw new InvalidArgumentException($errorMsg);
        }
    }

    /**
     * @throws InvalidSchemaException
     */
    public function userCanVote(Survey $survey, User $user): void
    {
        if (!$this->surveyVoteHandler->userCanVote($user, $survey)) {
            $errorMsg = 'User#'.$user->getId().
                ' already voted in Survey#'.$survey->getId().', Survey has not the participation-state or User has not proper permission.';
            throw new InvalidArgumentException($errorMsg);
        }
    }
}
