<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadSurveyData;
use demosplan\DemosPlanCoreBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanCoreBundle\Validator\SurveyValidator;
use Exception;
use InvalidArgumentException;

/**
 * Test mismatches between a Procedure and a Survey are detected and properly handled.
 *
 * Class ProcedureMatchSurveyTest
 */
class ProcedureMatchSurveyTest extends SurveyTestUtils
{
    /** @var SurveyValidator */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyValidator::class);
    }

    /**
     * Tests that a Survey which belongs to a Procedure is accepted as such.
     *
     * @throws Exception
     */
    public function testSurveyBelongsToProcedure(): void
    {
        $procedureId = $this->getProcedureIdByReference('testProcedure2');
        $surveyId = $this->fixtures->getReference(LoadSurveyData::SCHOOL_SURVEY)->getId();
        $accepted = true;
        try {
            $this->sut->surveyBelongsToProcedure($procedureId, $surveyId);
        } catch (InvalidArgumentException $e) {
            $accepted = false;
        }
        $this->assertTrue($accepted, 'Survey belongs to Procedure but was rejected');
    }

    /**
     * Tests that a Survey which does not belong to a Procedure is detected and handled with
     * the proper SurveyInputDataException code.
     *
     * @throws Exception
     */
    public function testSurveyDoesNotBelongToProcedure(): void
    {
        $procedureId = $this->getProcedureIdByReference('testProcedure2');
        $surveyId = $this->fixtures->getReference(LoadSurveyData::POOL_SURVEY)->getId();
        $properException = false;
        try {
            $this->sut->surveyBelongsToProcedure($procedureId, $surveyId);
        } catch (InvalidArgumentException $e) {
            $properException = true;
            $this->assertEquals(
                SurveyInputDataException::SURVEY_NOT_IN_PROCEDURE,
                $e->getCode(),
                'Survey not belonging to Procedure not handled with proper exception code.'
            );
        }
        $this->assertTrue(
            $properException,
            'Survey not belonging to Procedure was not handled with the proper exception.'
        );
    }

    /**
     * Test that an existing Procedure is validated as such.
     *
     * @throws Exception
     */
    public function testExistingProcedure(): void
    {
        $procedureId = $this->getProcedureIdByReference('testProcedure2');
        $accepted = true;
        try {
            $this->sut->procedureExists($procedureId);
        } catch (InvalidArgumentException $e) {
            $accepted = false;
        }
        $this->assertTrue($accepted, 'Existing procedure was not validated.');
    }

    /**
     * Test a nonexistent procedure is detected and handled with the proper
     * SurveyInputDataException code.
     *
     * @throws Exception
     */
    public function testNonExistentProcedure(): void
    {
        $nonExistentProcedureId = 'i-am-so-nonexistent';
        $properException = false;
        try {
            $this->sut->procedureExists($nonExistentProcedureId);
        } catch (InvalidArgumentException $e) {
            $properException = true;
            $this->assertEquals(
                SurveyInputDataException::NONEXISTENT_PROCEDURE,
                $e->getCode(),
                'Nonexistent Procedure was not handled with the proper exception code'
            );
        }
        $this->assertTrue(
            $properException,
            'Nonexistent procedure was not handled with the proper exception.'
        );
    }

    /**
     * Test a Survey with an end date before the Procedure's end date is validated.
     *
     * @throws Exception
     */
    public function testSurveyEndDateBeforeProcedureEndDate(): void
    {
        $procedure = $this->getProcedureByReference('testProcedure');
        $procedure->setPublicParticipationEndDate(new DateTime('2020-06-10'));

        $inputArray = $this->getValidInputArray();
        $inputArray['endDate'] = '2020-05-10';

        $accepted = true;
        try {
            $this->sut->validateSurveyDates(
                $procedure,
                $inputArray
            );
        } catch (SurveyInputDataException $e) {
            $accepted = false;
        }
        $this->assertTrue($accepted, 'Survey endDate before Procedure endDate not validated');
    }

    /**
     * Test a Survey with an end date after the Procedure's end date is detected and handled
     * with the proper SurveyInputDataException code.
     *
     * @throws Exception
     */
    public function testSurveyEndDateAfterProcedureEndDate(): void
    {
        $procedure = $this->getProcedureByReference('testProcedure');
        $procedure->setPublicParticipationPhase('participation');
        $procedure->setPublicParticipationEndDate(new DateTime('2020-05-10'));

        $inputArray = $this->getValidInputArray();
        $inputArray['endDate'] = '2020-06-10';
        $inputArray['status'] = 'participation';

        $properException = false;
        try {
            $this->sut->validateSurveyDates(
                $procedure,
                $inputArray
            );
        } catch (SurveyInputDataException $e) {
            $properException = true;
            $this->assertEquals(
                SurveyInputDataException::END_DATE_AFTER_END_PROCEDURE,
                $e->getCode(),
                'Survey\'s end date after Procedure\'s end date not handled with proper '.
                'exception code'
            );
        }
        $this->assertTrue(
            $properException,
            'Survey\'s end date after Procedure\'s end date not handled with proper exception'
        );
    }
}
