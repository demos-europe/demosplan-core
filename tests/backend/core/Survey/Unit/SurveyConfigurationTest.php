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
use demosplan\DemosPlanCoreBundle\Logic\Survey\SurveyHandler;

class SurveyConfigurationTest extends SurveyTestUtils
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SurveyHandler::class);
    }

    /**
     * Test that there is a file configured as json schema for Survey update.
     */
    public function testJsonSchemaUpdateExists(): void
    {
        $jsonSchemaPathUpdate = $this->getContainer()->getParameter(
            'survey.schema_path_update'
        );
        $this->assertFileExists($jsonSchemaPathUpdate);
    }

    /**
     * Test that there is a file configured as json schema for Survey create.
     */
    public function testJsonSchemaCreateExists(): void
    {
        $jsonSchemaPathCreate = $this->getContainer()->getParameter(
            'survey.schema_path_create'
        );
        $this->assertFileExists($jsonSchemaPathCreate);
    }

    /**
     * Test that configured default status for a Survey meet the expected default status.
     */
    public function testConfigValidDefaultStatus(): void
    {
        $configDefaultStatus = $this->getContainer()->getParameter('survey.status.default');
        $this->assertContains(
            $configDefaultStatus,
            $this->getContainer()->getParameter('survey.statuses')
        );
    }

    /**
     * Tests that configured valid statuses match expected statuses.
     */
    public function testConfigValidStatus(): void
    {
        $configValidStatuses = $this->getContainer()->getParameter('survey.statuses');
        $expectedValidStatusesJson = Json::encode($this->expectedValidStatuses);
        $configValidStatusesJson = Json::encode($configValidStatuses);

        $errorMsg = "Configuration valid Survey statuses \n\t$configValidStatusesJson\n".
            "don't match expected values.\n\t$expectedValidStatusesJson\n";

        $this->assertEquals($this->expectedValidStatuses, $configValidStatuses, $errorMsg);
    }
}
