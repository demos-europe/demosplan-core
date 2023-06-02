<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Survey;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Survey\Survey;
use Exception;

class SurveyNewHandler
{
    /** @var string */
    private $defaultStatus;

    public function __construct(string $defaultStatus)
    {
        $this->defaultStatus = $defaultStatus;
    }

    /**
     * @throws Exception
     */
    public function getSurveyDefaults(Procedure $procedure): Survey
    {
        /** @var Survey $survey */
        $survey = new Survey();
        $survey->setTitle('');
        $survey->setDescription('');
        $survey->setStartDate(new DateTime());
        $survey->setEndDate($procedure->getPublicParticipationEndDate());
        $survey->setStatus($this->defaultStatus);
        $survey->setProcedure($procedure);
        $survey->setId('');

        return $survey;
    }
}
