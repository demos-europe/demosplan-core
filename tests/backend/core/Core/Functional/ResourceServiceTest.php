<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use Tests\Base\FunctionalTestCase;

class ResourceServiceTest extends FunctionalTestCase
{
    /** @var JsonApiActionService */
    protected $sut;

    /**
     * @var StatementResourceType
     */
    private $statementResourceType;

    /**
     * @var OrgaResourceType
     */
    private $orgaResourceType;

    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;

    public function setUp(): void
    {
        parent::setUp();

        /* @var JsonApiActionService sut */
        $this->sut = self::getContainer()->get(JsonApiActionService::class);
        $this->conditionFactory = self::getContainer()->get(DqlConditionFactory::class);
        $this->statementResourceType = self::getContainer()->get(StatementResourceType::class);
        $this->orgaResourceType = self::getContainer()->get(OrgaResourceType::class);
    }

    /**
     * Test to check if the test setup works at all.
     */
    public function testTrue(): void
    {
        self::assertTrue(true);
    }

    public function testStatementGet(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_json_api_statement',
            // the following two should not be needed
            // but are anyway for internal reasons currently
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);

        $expected = $this->getStatementReference('testStatement');
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());
        $actual = $this->statementResourceType->getEntity($expected->getId());

        self::assertSame($expected, $actual);
    }

    public function testStatementAuthorFilter(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_json_api_statement',
            // the following two should not be needed
            // but are anyway for internal reasons currently
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);

        $expected = $this->getStatementReference('testStatement');
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());

        self::assertSame('Max Mustermann', $expected->getAuthorName());
        self::assertSame($currentProcedureService->getProcedure(), $expected->getProcedure());
        self::assertFalse($expected->isOriginal());
        self::assertFalse($expected->isDeleted());
        self::assertNull($expected->getHeadStatement());

        $listResult = $this->statementResourceType->getEntities([
            $this->conditionFactory->propertyHasValue(
                $expected->getAuthorName(),
                $this->statementResourceType->authorName
            ),
        ], []);

        self::assertCount(1, $listResult);
        self::assertSame($expected, $listResult[0]);
    }

    public function testOrgaMasterToebFilter(): void
    {
        self::markSkippedForCIIntervention();

        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_mastertoeblist',
            'area_manage_orgas',
        ]);

        $expected = $this->getOrgaReference('orgaWithMasterToeb');
        $masterToeb = $expected->getMasterToeb();

        self::assertNotNull($masterToeb);
        self::assertFalse($expected->isDeleted());

        $listResult = $this->orgaResourceType->getEntities(
            [$this->conditionFactory->propertyIsNotNull($this->orgaResourceType->masterToeb)], []
        );

        self::assertCount(1, $listResult);
        self::assertSame($expected, $listResult[0]);
    }

    public function testUnsegmentedStatementsFilter(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_json_api_statement',
            'feature_json_api_statement_segment',
            // the following two should not be needed
            // but are anyway for internal reasons currently
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);

        $expected = $this->getStatementReference('testStatement');
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());

        self::assertEmpty($expected->getSegmentsOfStatement());

        $listResult = $this->statementResourceType->getEntities(
            [$this->conditionFactory->propertyHasSize(0, $this->statementResourceType->segments)], []
        );

        self::assertGreaterThan(1, $listResult);
        self::assertContains($expected, $listResult);
    }
}
