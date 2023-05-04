<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Exception;

class SurveyInputDataException extends DemosException
{
    public const START_DATE_AFTER_END_DATE = 1;
    public const END_DATE_AFTER_END_PROCEDURE = 2;
    public const NONEXISTENT_PROCEDURE = 3;
    public const SURVEY_NOT_IN_PROCEDURE = 4;
    public const SURVEY_EVALUATION_IN_WRONG_PROCEDURE_STATUS = 5;
    public const MISSING_START_DATE = 6;
    public const MISSING_END_DATE = 7;

    public function __construct(string $usrMsg, string $logMsg, string $code)
    {
        parent::__construct($usrMsg, $logMsg, $code);
        $this->code = $code;
    }
}
