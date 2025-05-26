<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Survey\Unit;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\SurveyInputDataException;
use demosplan\DemosPlanCoreBundle\Validator\SurveyValidator;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;

class SurveyValidJsonTest extends SurveyTestUtils
{
    /** @var SurveyValidator */
    protected $sut;

    /** @var array */
    protected $wrongDateFormats;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wrongDateFormats = [
            'wrong-format',
            '01/10/2020',
            '2020/03/10',
            '2020/20/10',
            '2020/10/40',
            '01.10.2020',
            '2020.03.10',
            '2020.20.10',
            '2020.10.40',
            '01-10-2020',
            '2020-20-10',
        ];
        /* @var SurveyValidator sut */
        $this->sut = self::getContainer()->get(SurveyValidator::class);
    }

    /**
     * @throws Exception
     */
    public function testAllValidStatusUpdate(): void
    {
        $this->checkAllValidStatus('update');
    }

    /**
     * @throws Exception
     */
    public function testAllValidStatusCreate(): void
    {
        $this->checkAllValidStatus('create');
    }

    /**
     * Test that a valid json with every possible valid status is accepted by the schema.
     *
     * @throws Exception
     */
    private function checkAllValidStatus(string $mode): void
    {
        $schemaFilePath = $this->getJsonSchemaFilePath($mode);
        $validStatuses = $this->getContainer()->getParameter('survey.statuses');
        $validStatusesJson = Json::encode($validStatuses);
        $errorMsg = "Configured valid statuses \n\t$validStatusesJson\n won't match ".
                    "statuses in json schema \n\t$schemaFilePath\n";
        $validInputArray = $this->getValidInputArray($mode);
        foreach ($validStatuses as $validStatus) {
            $validInputArray['status'] = $validStatus;
            $validInputJson = Json::encode($validInputArray);
            $this->checkSchemaValidity($validInputJson, true, $errorMsg, $mode);
        }
    }

    /**
     * Test wrong date formats are detected for startDate. (Expected format 'yyyy-mm-dd').
     *
     * @throws Exception
     */
    public function testInvalidStartDateFormat(): void
    {
        foreach ($this->wrongDateFormats as $wrongDateFormat) {
            $this->checkWrongDateFormat('startDate', $wrongDateFormat);
        }
    }

    /**
     * Test wrong date formats are detected for endDate. (Expected format 'yyyy-mm-dd').
     *
     * @throws Exception
     */
    public function testInvalidEndDateFormat(): void
    {
        foreach ($this->wrongDateFormats as $wrongDateFormat) {
            $this->checkWrongDateFormat('endDate', $wrongDateFormat);
        }
    }

    /**
     * Test giving an endDate previous to startDate is detected and handled with
     * the proper exception code.
     *
     * @throws Exception
     */
    public function testStartDateBeforeEndDate(): void
    {
        self::markSkippedForCIIntervention();

        $validInputArray = $this->getValidInputArray();
        $validInputArray['startDate'] = '2020-10-01';
        $validInputArray['endDate'] = '2020-09-01';
        $validInputArray['status'] = 'participation';
        $validInputJson = Json::encode($validInputArray);
        $properException = false;
        try {
            $jsonSchemaPath = $this->getJsonSchemaFilePath();
            $this->sut->validSurveyData($validInputJson, $jsonSchemaPath);
        } catch (SurveyInputDataException $e) {
            $properException = true;
            $this->assertEquals(
                SurveyInputDataException::START_DATE_AFTER_END_DATE,
                $e->getCode(),
                'EndDate previous to startDate was not handled with the proper Exception code'
            );
        }
        $this->assertTrue(
            $properException,
            'An end date previous to start date was accepted'
        );
    }

    /**
     * Given a date field in the input data and a wrong format date, checks that such
     * error is detected.
     *
     * @throws Exception
     */
    private function checkWrongDateFormat(string $field, $wrongDate): void
    {
        $validInputArray = $this->getValidInputArray();
        $validInputArray[$field] = $wrongDate;
        $invalidInputJson = Json::encode($validInputArray, true);
        $errorMsg = "$field ('$wrongDate') has a wrong format but was accepted";
        $this->checkSchemaValidity($invalidInputJson, false, $errorMsg);
    }

    /**
     * Check that a given json is validated as expected.
     *
     * @throws Exception
     */
    private function checkSchemaValidity(
        string $json,
        bool $mustBeValid,
        string $errorMsg,
        string $mode = 'update',
    ): void {
        try {
            $schemaFilePath = $this->jsonSchemaPathUpdate;
            if ('create' === $mode) {
                $schemaFilePath = $this->jsonSchemaPathCreate;
            }
            $validSchema = true;
            $this->sut->validSurveyData($json, $schemaFilePath);
        } catch (InvalidSchemaException $e) {
            if ($mustBeValid) {
                $this->logger->error($e);
            }
            $validSchema = false;
        } catch (SurveyInputDataException $e) {
            $this->logger->error($e->getMessage());
        }
        $this->assertSame($validSchema, $mustBeValid, $errorMsg);
    }
}
