<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdf;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AnnotatedStatementPdfResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OrgaResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use Tests\Base\FunctionalTestCase;

class ResourceServiceTest extends FunctionalTestCase
{
    /** @var JsonApiActionService */
    protected $sut;

    /**
     * @var AnnotatedStatementPdfResourceType
     */
    private $annotatedStatementPdfResourceType;

    /**
     * @var StatementResourceType
     */
    private $statementResourceType;

    /**
     * @var OrgaResourceType
     */
    private $orgaResourceType;

    /**
     * @var EntityFetcher
     */
    private $entityFetcher;

    /**
     * @var ConditionFactoryInterface
     */
    private $conditionFactory;

    public function setUp(): void
    {
        parent::setUp();

        /* @var JsonApiActionService sut */
        $this->sut = self::$container->get(JsonApiActionService::class);
        $this->entityFetcher = self::$container->get(EntityFetcher::class);
        $this->conditionFactory = self::$container->get(DqlConditionFactory::class);
        $this->annotatedStatementPdfResourceType = self::$container->get(AnnotatedStatementPdfResourceType::class);
        $this->statementResourceType = self::$container->get(StatementResourceType::class);
        $this->orgaResourceType = self::$container->get(OrgaResourceType::class);
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
        $currentProcedureService = self::$container->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());

        $actual = $this->entityFetcher->getEntityAsReadTarget(
            $this->statementResourceType,
            $expected->getId()
        );

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
        $currentProcedureService = self::$container->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());

        self::assertSame('Max Mustermann', $expected->getAuthorName());
        self::assertSame($currentProcedureService->getProcedure(), $expected->getProcedure());
        self::assertFalse($expected->isOriginal());
        self::assertFalse($expected->isDeleted());
        self::assertNull($expected->getHeadStatement());

        $listResult = $this->entityFetcher->listEntities(
            $this->statementResourceType,
            [$this->conditionFactory->propertyHasValue(
                $expected->getAuthorName(),
                $this->statementResourceType->authorName
            )],
        );

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

        $listResult = $this->entityFetcher->listEntities(
            $this->orgaResourceType,
            [$this->conditionFactory->propertyIsNotNull($this->orgaResourceType->masterToeb)]
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
        $currentProcedureService = self::$container->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());

        self::assertEmpty($expected->getSegmentsOfStatement());

        $listResult = $this->entityFetcher->listEntities(
            $this->statementResourceType,
            [$this->conditionFactory->propertyHasSize(0, $this->statementResourceType->segments)]
        );

        self::assertGreaterThan(1, $listResult);
        self::assertContains($expected, $listResult);
    }

    public function testAnnotatedStatementPdfCreate(): void
    {
        self::markSkippedForCIIntervention();
        // fails for unknown reason ("ResourceNotFoundException : No resource available for the type Procedure and ID EF84C833-F80C-43AE-882C-0AEA82F8BCD9")

        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_import_statement_pdf',
            'feature_json_api_procedure',
            'area_manage_orgas',
            'feature_json_api_procedure',
        ]);

        $file = $this->getFileReference('testFile');
        $procedure = $this->getProcedureReference('testProcedure4');

        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::$container->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($procedure);

        /** @var AnnotatedStatementPdf $actual */
        $actual = $this->sut->createObject(
            $this->annotatedStatementPdfResourceType,
            [],
            [
                'file' => [
                    'data' => [
                        'id'   => $file->getId(),
                        'type' => 'File',
                    ],
                ],
                'procedure' => [
                    'data' => [
                        'id'   => $procedure->getId(),
                        'type' => 'Procedure',
                    ],
                ],
                'annotatedStatementPdfPages' => ['data' => []],
            ]
        );

        self::assertInstanceOf(AnnotatedStatementPdf::class, $actual);
        self::assertSame($procedure, $actual->getProcedure());
        self::assertEmpty($actual->getAnnotatedStatementPdfPages());
        self::assertSame($file, $actual->getFile());
        self::assertSame(AnnotatedStatementPdf::PENDING, $actual->getStatus());
    }
}
