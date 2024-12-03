<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFragmentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\StatementNameTooLongException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use Illuminate\Support\Collection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Base\FunctionalTestCase;
use Throwable;
use Zenstruck\Foundry\Persistence\Proxy;

class StatementHandlerTest extends FunctionalTestCase
{
    /** @var StatementHandler */
    protected $sut;

    /** @var StatementFragment */
    protected $testStatementFragment;

    /** @var User */
    protected $testUser;

    /** @var Procedure */
    protected $testProcedure;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function setUp(): void
    {
        parent::setUp();

        // create test request, just so we have a request to attach the mock session to
        $request = new Request([], [], [], [], [], [], json_encode([
            'foo' => 'bar',
        ]));
        // add mocked session
        $request->setSession($this->getSessionMock());

        // add the request with mock session to the requestStack in the container
        $requestStack = $this->getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        /* @var StatementHandler sut */
        $this->sut = self::$container->get(StatementHandler::class);

        // generiere ein Stub vom GlobalConfig
        /** @var GlobalConfigInterface $stub */
        $stub = $this->getMockBuilder(GlobalConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->sut->setDemosplanConfig($stub);
        $this->globalConfig = $stub;

        $this->testStatementFragment = $this->fixtures->getReference('testStatementFragment1');

        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($this->testUser);
        $this->testProcedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);

        $permissions = $this->sut->getPermissions();
        $permissions->initPermissions($this->testUser);
        $permissions->enablePermissions(['feature_statements_fragment_edit']);
        $this->sut->setPermissions($permissions);
    }

    public function testCalculateDeletingNoAreaInformation()
    {
        $this->testStatementFragment = $this->fixtures->getReference('testStatementFragmentFilled');
        static::assertNotEmpty($this->testStatementFragment->getCounties());
        static::assertNotEmpty($this->testStatementFragment->getPriorityAreas());
        static::assertNotEmpty($this->testStatementFragment->getMunicipalities());

        // that means NO AreaInformation will be deleted:
        $inputData = [
            'priorityAreas'  => [$this->fixtures->getReference('testPriorityArea1')->getId(), $this->fixtures->getReference('testPriorityArea2')->getId()],
            'counties'       => [$this->fixtures->getReference('testCounty1')->getId(), $this->fixtures->getReference('testCounty2')->getId()],
            'municipalities' => [$this->fixtures->getReference('testMunicipality1')->getId(), $this->fixtures->getReference('testMunicipality2')->getId()],
        ];

        $result = $this->sut->calculateParentPropertyIdsToDelete($inputData, $this->testStatementFragment);
        static::assertEmpty($result['priorityAreaIds']);
        static::assertEmpty($result['municipalityIds']);
        static::assertEmpty($result['countyIds']);
    }

    public function testCalculateDeletingNotExistingAreaInformation()
    {
        // used Fragment has no areaInformation!:
        static::assertEmpty($this->testStatementFragment->getCounties());
        static::assertEmpty($this->testStatementFragment->getPriorityAreas());
        static::assertEmpty($this->testStatementFragment->getMunicipalities());

        // the following AreaInformation will NOT be deleted:
        // conflicting input: county which will not deleted is not even in the fragment
        $inputData = [
            'priorityAreas'  => [],
            'counties'       => [$this->fixtures->getReference('testCounty1')->getId()],
            'municipalities' => [],
        ];

        $result = $this->sut->calculateParentPropertyIdsToDelete($inputData, $this->testStatementFragment);
        static::assertEmpty($result['priorityAreaIds']);
        static::assertEmpty($result['municipalityIds']);
        static::assertEmpty($result['countyIds']);
    }

    public function testCalculateDeletingAreaInformation()
    {
        $this->testStatementFragment = $this->fixtures->getReference('testStatementFragmentFilled');

        static::assertNotEmpty($this->testStatementFragment->getCounties());
        static::assertNotEmpty($this->testStatementFragment->getPriorityAreas());
        static::assertNotEmpty($this->testStatementFragment->getMunicipalities());

        // the following AreaInformation will NOT be deleted:
        $inputData = [
            'priorityAreas'  => [$this->fixtures->getReference('testPriorityArea1')->getId()],
            'counties'       => [$this->fixtures->getReference('testCounty1')->getId()],
            'municipalities' => [$this->fixtures->getReference('testMunicipality1')->getId()],
        ];

        $result = $this->sut->calculateParentPropertyIdsToDelete($inputData, $this->testStatementFragment);
        static::assertCount(1, $result['priorityAreaIds']);
        static::assertContains($this->fixtures->getReference('testPriorityArea2')->getId(), $result['priorityAreaIds']);

        static::assertCount(1, $result['municipalityIds']);
        static::assertContains($this->fixtures->getReference('testMunicipality2')->getId(), $result['municipalityIds']);

        static::assertCount(1, $result['countyIds']);
        static::assertContains($this->fixtures->getReference('testCounty2')->getId(), $result['countyIds']);
    }

    public function testCalculateDeletingAllAreaInformation()
    {
        $this->testStatementFragment = $this->fixtures->getReference('testStatementFragmentFilled');

        static::assertNotEmpty($this->testStatementFragment->getCounties());
        static::assertNotEmpty($this->testStatementFragment->getPriorityAreas());
        static::assertNotEmpty($this->testStatementFragment->getMunicipalities());

        // the following AreaInformation will NOT be deleted:
        $inputData = [
            'priorityAreas'  => [],
            'counties'       => [],
            'municipalities' => [],
        ];

        $result = $this->sut->calculateParentPropertyIdsToDelete($inputData, $this->testStatementFragment);
        static::assertCount(2, $result['priorityAreaIds']);
        static::assertContains($this->fixtures->getReference('testPriorityArea1')->getId(), $result['priorityAreaIds']);
        static::assertContains($this->fixtures->getReference('testPriorityArea2')->getId(), $result['priorityAreaIds']);

        static::assertCount(2, $result['municipalityIds']);
        static::assertContains($this->fixtures->getReference('testMunicipality1')->getId(), $result['municipalityIds']);
        static::assertContains($this->fixtures->getReference('testMunicipality2')->getId(), $result['municipalityIds']);

        static::assertCount(2, $result['countyIds']);
        static::assertContains($this->fixtures->getReference('testCounty1')->getId(), $result['countyIds']);
        static::assertContains($this->fixtures->getReference('testCounty2')->getId(), $result['countyIds']);
    }

    public function testGetIsolatedAreaInformation()
    {
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragment1');
        $resultCollection = $this->sut->getIsolatedInformationIds(
            ['priorityAreaIds' => [
                $this->fixtures->getReference('testPriorityArea1')->getId(),
                $this->fixtures->getReference('testPriorityArea2')->getId(),
            ],
            ],
            [$fragment]);

        static::assertInstanceOf(Collection::class, $resultCollection);
        // expect 4 arrays: $isolatedPriorityAreaIds, $isolatedMunicipalityIds, $isolatedCountyIds, $isolatedTagIds
        static::assertCount(4, $resultCollection);

        static::assertInstanceOf(Collection::class, $resultCollection['priorityAreas']);
        static::assertInstanceOf(Collection::class, $resultCollection['municipalities']);
        static::assertInstanceOf(Collection::class, $resultCollection['counties']);

        static::assertNotEmpty($resultCollection['priorityAreas']);
        static::assertCount(2, $resultCollection['priorityAreas']);
        static::assertContains($this->fixtures->getReference('testPriorityArea1')->getId(), $resultCollection['priorityAreas']);
        static::assertContains($this->fixtures->getReference('testPriorityArea2')->getId(), $resultCollection['priorityAreas']);

        static::assertEmpty($resultCollection['municipalities']);
        static::assertEmpty($resultCollection['counties']);
    }

    public function testIsIsolatedPriorityArea()
    {
        /* @var StatementFragment[] $fragments */
        $fragments[] = $this->testStatementFragment;
        $result = $this->sut->isPriorityAreaInFragments($this->fixtures->getReference('testPriorityArea1')->getId(), $fragments);
        static::assertFalse($result);

        $fragments[] = $this->fixtures->getReference('testStatementFragmentFilled');
        $result = $this->sut->isPriorityAreaInFragments($this->fixtures->getReference('testPriorityArea1')->getId(), $fragments);
        static::assertTrue($result);
    }

    public function testIsIsolatedCounty()
    {
        /* @var StatementFragment[] $fragments */
        $fragments[] = $this->testStatementFragment;
        $result = $this->sut->isCountyInFragments($this->fixtures->getReference('testCounty1')->getId(), $fragments);
        static::assertFalse($result);

        $fragments[] = $this->fixtures->getReference('testStatementFragmentFilled');
        $result = $this->sut->isCountyInFragments($this->fixtures->getReference('testCounty1')->getId(), $fragments);
        static::assertTrue($result);
    }

    public function testIsIsolatedMunicipality()
    {
        /* @var StatementFragment[] $fragments */
        $fragments[] = $this->testStatementFragment;
        $result = $this->sut->isMunicipalityInFragments($this->fixtures->getReference('testMunicipality1')->getId(), $fragments);
        static::assertFalse($result);

        $fragments[] = $this->fixtures->getReference('testStatementFragmentFilled');
        $result = $this->sut->isMunicipalityInFragments($this->fixtures->getReference('testMunicipality1')->getId(), $fragments);
        static::assertTrue($result);
    }

    /**
     * Test updating a StatementFragment with areainformations e.g. counties, which are already related
     * to the StatementFragment.
     * This update will fail in this test environment, while succeed in the actually environment, because
     * of the special type of proxy objects (lazy loaded) of this test envionment.
     */
    public function testAddExistingAreaInformationOnUpdateStatementFragment()
    {
        // On update with updateData which includes already existing relations (counties),
        // the update will fail in this test environment.
        // Because of different types of Objects ("native" demosplan-Object vs proxy object) the check of already
        // existing relation (doctrine sited?) are fail.
        self::markSkippedForCIIntervention();
        // In Test-Environment the check of already existing relations to objects will fail,
        // because of using of ProxieObjects instead of "native" DemosPlan-Objects

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentFilled1');
        $testCounty1 = $this->fixtures->getReference('testCounty1');
        $testCounty2 = $this->fixtures->getReference('testCounty2');
        $testPriorityArea1 = $this->fixtures->getReference('testPriorityArea1');
        $testPriorityArea2 = $this->fixtures->getReference('testPriorityArea2');
        $testMunicipality1 = $this->fixtures->getReference('testMunicipality1');
        $testMunicipality2 = $this->fixtures->getReference('testMunicipality2');

        $inputData = [
            'r_priorityAreas'  => [$testPriorityArea1->getId(), $testPriorityArea2->getId()],
            'r_municipalities' => [$testMunicipality1->getId(), $testMunicipality2->getId()],
            'r_counties'       => [$testCounty1->getId(), $testCounty2->getId()],
        ];

        // related Statement already have 3 area informations
        $relatedStatement = $this->sut->getStatement($fragment->getStatementId());
        static::assertNotContains($testPriorityArea2, $relatedStatement->getPriorityAreas());
        static::assertNotContains($testMunicipality2, $relatedStatement->getMunicipalities());
        static::assertNotContains($testCounty2, $relatedStatement->getCounties());
        static::assertContains($testMunicipality1, $relatedStatement->getMunicipalities());
        static::assertContains($testPriorityArea1, $relatedStatement->getPriorityAreas());
        static::assertContains($testCounty1, $relatedStatement->getCounties());

        static::assertNotContains($testPriorityArea2, $fragment->getPriorityAreas());
        static::assertNotContains($testMunicipality2, $fragment->getMunicipalities());
        static::assertNotContains($testCounty2, $fragment->getCounties());

        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->sut->setAssigneeOfStatementFragment($fragment, $user);

        $result = $this->sut->updateStatementFragment($fragment->getId(), $inputData, false);
        static::assertInstanceOf(StatementFragment::class, $result);

        // related Statement have now the 3 new area informations of the related fragment, too:
        $relatedStatement = $this->sut->getStatement($result->getStatementId());
        static::assertContains($testMunicipality1, $relatedStatement->getMunicipalities());
        static::assertContains($testMunicipality2, $relatedStatement->getMunicipalities());
        static::assertContains($testPriorityArea1, $relatedStatement->getPriorityAreas());
        static::assertContains($testPriorityArea2, $relatedStatement->getPriorityAreas());
        static::assertContains($testCounty1, $relatedStatement->getCounties());
        static::assertContains($testCounty2, $relatedStatement->getCounties());

        $infos = $this->sut->getMessageBag()->getInfo()->get('info');
        static::assertCount(3, $infos);
    }

    public function testDeletePriorityAreaOnUpdateStatementFragment()
    {
        self::markSkippedForCIIntervention();

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentFilled1');
        $permissions = $this->sut->getPermissions();
        $permissions->enablePermissions([
            'field_statement_county',
            'field_statement_municipality',
            'field_statement_priority_area',
        ]);
        $this->sut->setPermissions($permissions);
        $testCounty1 = $this->fixtures->getReference('testCounty1');
        $testPriorityArea1 = $this->fixtures->getReference('testPriorityArea1');
        $testPriorityArea2 = $this->fixtures->getReference('testPriorityArea2');
        $testMunicipality1 = $this->fixtures->getReference('testMunicipality1');

        $relatedStatement = $this->sut->getStatement($fragment->getStatementId());
        static::assertCount(1, $relatedStatement->getFragments());
        static::assertCount(1, $relatedStatement->getPriorityAreas());
        static::assertCount(1, $relatedStatement->getCounties());
        static::assertCount(1, $relatedStatement->getMunicipalities());
        static::assertContains($testPriorityArea1, $relatedStatement->getPriorityAreas());
        static::assertContains($testMunicipality1, $relatedStatement->getMunicipalities());
        static::assertContains($testCounty1, $relatedStatement->getCounties());

        // fragment are filled?
        static::assertCount(1, $fragment->getPriorityAreas());
        static::assertCount(1, $fragment->getMunicipalities());
        static::assertCount(1, $fragment->getCounties());
        static::assertContains($testPriorityArea1, $fragment->getPriorityAreas());
        static::assertContains($testMunicipality1, $fragment->getMunicipalities());
        static::assertContains($testCounty1, $fragment->getCounties());

        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);
        $this->sut->setAssigneeOfStatementFragment($fragment, $user);

        // delete all areaInformation of the related Fragment:
        $result = $this->sut->updateStatementFragment(
            $fragment->getId(),
            [
                'r_priorityAreas'  => [],
                'r_municipalities' => [],
                'r_counties'       => [],
            ],
            false);

        static::assertInstanceOf(StatementFragment::class, $result);

        // fragment have no more areainformation
        static::assertEmpty($fragment->getPriorityAreas());

        // the statement should be still have the areainformation:
        $relatedStatement = $this->sut->getStatement($result->getStatementId());
        static::assertContains($testPriorityArea1, $relatedStatement->getPriorityAreas());
        static::assertNotContains($testPriorityArea2, $relatedStatement->getPriorityAreas());
        static::assertCount(1, $relatedStatement->getPriorityAreas());

        $warnings = $this->sut->getMessageBag()->getWarning()->get('warning');
        static::assertCount(3, $warnings);

        $confirms = $this->sut->getMessageBag()->getConfirm()->get('confirm');
        static::assertCount(1, $confirms);
        static::assertContains('Die Änderungen am Datensatz '.$fragment->getDisplayId().' wurden gespeichert.', $confirms);
    }

    public function testNewPublicStatement()
    {
        self::markSkippedForCIElasticsearchUnavailable();
        // generiere ein Mockobjekt vom StorageService

        $this->setMocks();

        $this->sut->setRequestValues([
            'action'     => 'statementpublicnew',
            'r_privacy'  => 1,
            'r_text'     => 'MyFakeStatement',
            'r_loadtime' => time() - 10, ]);

        $this->sut->savePublicStatement('someId');
    }

    public function testImportTags(): void
    {

        $statementService = $this->getMockBuilder(
            StatementService::class
        )
            ->disableOriginalConstructor()
            ->getMock();

        $tagsBefore = $this->countEntries(Tag::class);
        $tagTopicsBefore = $this->countEntries(TagTopic::class);
        $this->sut->setRequestValues([
            'r_import'    => '',
            'r_importCsv' => 'asdfasdfasdf',
        ]);

        /* @var StatementService $statementService */
        $this->sut->setStatementService($statementService);
        $this->sut->importTags($this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(), fopen($this->getFileInfoTagImport()->getAbsolutePath(), 'rb'));
        $tagsAfter = $this->countEntries(Tag::class);
        $tagTopicsAfter = $this->countEntries(TagTopic::class);
        self::assertEquals($tagsBefore + 101, $tagsAfter);
        self::assertEquals($tagTopicsBefore + 16, $tagTopicsAfter);
    }

    /**
     * @throws Exception
     * @throws Throwable
     *
     * @dataProvider dataProviderValidationErrorNewStatement
     */
    public function testValidationerrorNewPublicStatement($request)
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->expectException(Exception::class);

        $this->setMocks();

        $this->sut->setRequestValues($request);
        $this->sut->savePublicStatement('someId');
    }

    /**
     * DataProvider.
     */
    public function dataProviderValidationErrorNewStatement(): array
    {
        return [
            [['r_privacy' => 1, 'r_text' => 'MyFakeStatement']],
            [['r_privacy' => 1, 'action' => 'statementpublicnew']],
            [['r_text' => 'MyFakeStatement']],
            [['r_loadtime' => time()]],
        ];
    }

    /**
     * dataProvider getStatementFragmentUpdateData.
     *
     * @throws Exception
     */
    public function testUpdateStatementFragmentData(/* $providerData */)
    {
        self::markSkippedForCIIntervention();

        $isReviewer = false;

        $permissions = $this->sut->getPermissions();
        $permissions->enablePermissions([
            'feature_statements_fragment_vote',
            'field_statement_county',
            'field_statement_municipality',
            'field_statement_priority_area',
            'feature_statements_fragment_edit',
            'feature_statements_fragment_consideration_advice',
            'feature_statements_fragment_consideration',
            'feature_statements_fragment_add_reviewer',
            'feature_statements_fragment_vote',
            'feature_statements_fragment_add',
            'field_fragment_status',
            'feature_single_document_fragment',
        ]);
        $this->sut->setPermissions($permissions);
        $mockStatementService = $this->getMockBuilder(StatementService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockStatementService->expects($this->once())
            ->method('updateStatementFragment')
            ->with($this->equalTo($providerData['expectedData']));

        $mockStatementService->expects($this->once())
            ->method('getStatementFragment')
            ->with($this->testStatementFragment->getId())
            ->willReturn($this->testStatementFragment);

        /* @noinspection PhpParamsInspection */
        $this->sut->setStatementService($mockStatementService);
        $this->sut->updateStatementFragment($this->testStatementFragment->getId(), $providerData['data'], $isReviewer);
    }

    /**
     * DataProvider.
     */
    public function getStatementFragmentUpdateData(): array
    {
        $id = $this->fixtures->getReference('testStatementFragment1')->getId();

        return [
            // for providing new Data <copy>
            [
                [
                    'data'         => [
                        'r_currentUserName' => 'userName',
                        'r_vote'            => 'full',
                    ],
                    'expectedData' => [
                        'id'                   => $id,
                        'archivedVoteUserName' => 'userName',
                        'vote'                 => 'full',
                    ],
                ],
            ],
            // zuweisen von fragment zu einem reviewer, welcher dann Empfehlungen von Begründung und abgeben kann.
            [
                [
                    'data'         => [
                        'r_reviewer' => 'department',
                    ],
                    'expectedData' => [
                        'id'           => $id,
                        'departmentId' => 'department',
                        'status'       => 'fragment.status.assignedToFB',
                    ],
                ],
            ],
            // set Vote
            [
                [
                    'data'         => [
                        'r_vote' => 'full',
                    ],
                    'expectedData' => [
                        'id'   => $id,
                        'vote' => 'full',
                    ],
                ],
            ],
            // Set consideration
            [
                [
                    'data'         => [
                        'r_consideration' => 'consideration text',
                    ],
                    'expectedData' => [
                        'id'            => $id,
                        'consideration' => 'consideration text',
                    ],
                ],
            ],
        ];
    }

    /**
     * dataProvider getStatementFragmentUpdateDataAsReviewer.
     *
     * @throws Exception
     */
    public function testUpdateStatementFragmentDataAsReviewer(/* $providerData */)
    {
        self::markSkippedForCIIntervention();

        $isReviewer = true;

        $mock = $this->getMockBuilder(
            StatementService::class
        )
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('updateStatementFragment')
            ->with(
                $this->equalTo($providerData['expectedData'])
            );
        $mock->expects($this->once())
            ->method('getStatementFragment')
            ->with($this->testStatementFragment->getId())
            ->willReturn($this->testStatementFragment);

        /* @noinspection PhpParamsInspection */
        $this->sut->setStatementService($mock);
        $this->sut->updateStatementFragment($this->testStatementFragment->getId(), $providerData['data'], $isReviewer);
    }

    /**
     * DataProvider.
     */
    public function getStatementFragmentUpdateDataAsReviewer(): array
    {
        $id = $this->fixtures->getReference('testStatementFragment1')->getId();

        return [
            // abgeben einer Empfehlung, wobei der userName nicht gespeicherst wird.
            [
                [
                    'data'         => [
                        'r_currentUserName' => 'userName',
                        'r_vote_advice'     => 'full',
                    ],
                    'expectedData' => [
                        'id'             => $id,
                        'voteAdvice'     => 'full',
                        'counties'       => [],
                        'municipalities' => [],
                        'priorityAreas'  => [],
                        'tags'           => [],
                    ],
                ],
            ],
            // zurückweisen eines Datensatzes, wobei die departmentId auf null gesetzt werden soll und die abgegebene
            // considerationAdvice in das consideration feld kopiert werden soll
            [
                [
                    'data'         => [
                        'r_notify'         => 'destinationDepartment',
                        'r_departmentName' => 'archivedNameOfDepartment',
                        'r_orgaName'       => 'archivedNameOfOrga',
                    ],
                    'expectedData' => [
                        'id'                                     => $id,
                        'departmentId'                           => null,
                        'copyConsiderationAdviceToConsideration' => true,
                        'archivedDepartmentName'                 => 'archivedNameOfDepartment',
                        'archivedOrgaName'                       => 'archivedNameOfOrga',
                        'counties'                               => [],
                        'municipalities'                         => [],
                        'priorityAreas'                          => [],
                        'tags'                                   => [],
                    ],
                ],
            ],
            // set Vote
            [
                [
                    'data'         => [
                        'r_vote_advice' => 'full',
                    ],
                    'expectedData' => [
                        'id'             => $id,
                        'voteAdvice'     => 'full',
                        'counties'       => [],
                        'municipalities' => [],
                        'priorityAreas'  => [],
                        'tags'           => [],
                    ],
                ],
            ],
            // Set consideration
            [
                [
                    'data'         => [
                        'r_consideration' => 'consideration text',
                    ],
                    'expectedData' => [
                        'id'             => $id,
                        'consideration'  => 'consideration text',
                        'counties'       => [],
                        'municipalities' => [],
                        'priorityAreas'  => [],
                        'tags'           => [],
                    ],
                ],
            ],
        ];
    }

    public function testUpdateStatementFragmentDataNotExistant()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(EntityNotFoundException::class);

        $mock = $this->getMockBuilder(
            StatementService::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->once())
            ->method('getStatementFragment')
            ->willReturn(null);
        /* @noinspection PhpParamsInspection */
        $this->sut->setStatementService($mock);
        $this->sut->updateStatementFragment('notExistant', [], false);

        fail('Exception expected');
    }

    public function testCreateStatementFragment()
    {
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssigned4');
        $statementId = $fragment->getStatementId();
        $procedureId = $fragment->getProcedureId();

        $countyId1 = $this->fixtures->getReference('testCounty1')->getId();
        $countyId2 = $this->fixtures->getReference('testCounty2')->getId();

        $municipalityId1 = $this->fixtures->getReference('testMunicipality1')->getId();
        $municipalityId2 = $this->fixtures->getReference('testMunicipality2')->getId();

        $priorityAreaId1 = $this->fixtures->getReference('testPriorityArea1')->getId();
        $priorityAreaId2 = $this->fixtures->getReference('testPriorityArea2')->getId();

        $testUserId3 = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN)->getId();
        $testDepartmentId = $this->fixtures->getReference('testDepartment')->getId();
        $testElementId1 = $this->fixtures->getReference('testElement1')->getId();

        $fragmentData = [
            'r_text'                   => 'neuer Text eines frisch erstellen Datensatzes.',
            'r_counties'               => [$countyId2, $countyId1],
            'r_municipalities'         => [$municipalityId1, $municipalityId2],
            'r_priorityAreas'          => [$priorityAreaId2, $priorityAreaId1],
            'r_modifiedByUserId'       => $testUserId3,
            'r_modifiedByDepartmentId' => $testDepartmentId,
            'r_element'                => $testElementId1,
            //            'r_paragraph' => 'neuer Text eines frisch erstellen Datensatzes.',
            //            'r_document' => 'neuer Text eines frisch erstellen Datensatzes.',
            'statementId'              => $statementId,
            'procedureId'              => $procedureId,
        ];

        $newFragment = $this->sut->createStatementFragment($fragmentData);
        static::assertInstanceOf(StatementFragment::class, $newFragment);

        static::assertEquals('fragment.status.new', $newFragment->getStatus());
        static::assertEquals($fragmentData['r_text'], $newFragment->getText());
        static::assertEquals($fragmentData['statementId'], $newFragment->getStatementId());
        static::assertEquals($fragmentData['procedureId'], $newFragment->getProcedureId());
        static::assertEquals($fragmentData['r_element'], $newFragment->getElement()->getId());
        static::assertEquals($fragmentData['r_modifiedByDepartmentId'], $newFragment->getModifiedByDepartmentId());
        static::assertEquals($fragmentData['r_modifiedByUserId'], $newFragment->getModifiedByUserId());
        static::assertEquals($fragmentData['r_priorityAreas'], $newFragment->getPriorityAreaIds());
        static::assertEquals($fragmentData['r_municipalities'], $newFragment->getMunicipalityIds());
        static::assertEquals($fragmentData['r_counties'], $newFragment->getCountyIds());
    }

    public function testAddBoilerplatesOfTag()
    {
        self::markSkippedForCIIntervention();

        // get fragment

        // get text
        // add tag

        // get fragment
        // get text assert ++

        //        $data['r_tags'] = [''];
        //        $statementFragmentData['consideration'] = '';
        //        $statementService = $this->statementServie
        //        //worked once:
        //        foreach ($data['r_tags'] as $tagId) {
        //            $tag = null;
        //            try {
        //                $tag = $statementService->getTag($tagId);
        //                if (!$tag instanceof Tag) {
        //                    continue;
        //                }
        //                $tags[] = $tag;
        //
        //                // add boilerplate if defined
        //                if (is_null($tag->getBoilerplate())) {
        //                    continue;
        //                }
        //                $statementFragmentData['consideration'] = isset($statementFragmentData['consideration']) ? $statementFragmentData['consideration'] : '';
        //                $statementFragmentData['consideration'] .= '<p>'.$tag->getBoilerplate()->getText().'</p>';
        //            } catch (\Exception $e) {
        //                $this->logger->warning("Could not resolve Tag with ID: ".$tagId);
        //                continue;
        //            }
        //
        //        }
        //
        //        $statementFragmentData['consideration'] = '';
        //
        //        $this->sut->addBoilerplatesOfTags($data['r_tags'], $statementFragmentData['consideration']);
        //
    }

    /**
     * @throws Exception
     */
    public function testGetNewAttachedTags()
    {
        self::markSkippedForCIIntervention();

        // load reference fragment
        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled1');
        $tag1 = $this->getTagReference('testFixtureTag_1');
        $tag2 = $this->getTagReference('testFixtureTag_2');
        $tag3 = $this->getTagReference('testFixtureTag_3');
        $tags = $fragment->getTags()->getValues();
        static::assertCount(0, $tags);

        $newTags = $this->sut->getNewAttachedTags($fragment, [$tag1->getId(), $tag2->getId(), $tag3->getId()]);

        static::assertInstanceOf(Collection::class, $newTags);
        static::assertCount(3, $newTags);
        static::assertContains($tag1->getId(), $newTags);
        static::assertContains($tag2->getId(), $newTags);
        static::assertContains($tag3->getId(), $newTags);

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);
        $this->sut->setAssigneeOfStatementFragment($fragment, $user);

        // add tags
        $result = $this->sut->updateStatementFragment(
            $fragment->getId(),
            ['r_tags' => [$tag1->getId(), $tag2->getId()]],
            false
        );

        static::assertInstanceOf(StatementFragment::class, $result);

        $fragment = $this->sut->getStatementFragment($fragment->getId());
        $newTags = $this->sut->getNewAttachedTags($fragment, [$tag1->getId(), $tag2->getId(), $tag3->getId()]);

        static::assertInstanceOf(Collection::class, $newTags);
        static::assertCount(1, $newTags);
        static::assertContains($tag3->getId(), $newTags);
    }

    public function testGetAssigneeOfFragment()
    {
        $fragmentId = $this->fixtures->getReference('testStatementFragment1')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertNull($fragment->getAssignee());

        $fragmentId = $this->fixtures->getReference('testStatementFragmentAssigned4')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentId);
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        static::assertEquals($user, $fragment->getAssignee());
    }

    public function testGetAssigneeOfStatement()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $statementId = $this->fixtures->getReference('testStatement1')->getId();
        $statement = $this->sut->getStatement($statementId);
        static::assertNull($statement->getAssignee());

        $statementId = $this->fixtures->getReference('testStatementAssigned6')->getId();
        $statement = $this->sut->getStatement($statementId);
        static::assertEquals($user, $statement->getAssignee());
    }

    public function testSetAssigneeOfFragment()
    {
        $fragmentId = $this->fixtures->getReference('testStatementFragment1')->getId();

        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertNull($fragment->getAssignee());

        /** @var User $user */
        $user = $this->fixtures->getReference('testUserPlanningOffice');
        $this->sut->setAssigneeOfStatementFragment($fragment, $user);
        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertEquals($user, $fragment->getAssignee());

        // test "stealing" the Statement
        /** @var User $user2 */
        $user2 = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);
        $this->sut->setAssigneeOfStatementFragment($fragment, $user2);
        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertEquals($user2, $fragment->getAssignee());

        // test "unlock" the Statement
        $this->sut->setAssigneeOfStatementFragment($fragment);
        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertNull($fragment->getAssignee());
    }

    public function testSetAssigneeOfStatement()
    {
        $statementId = $this->fixtures->getReference('testStatement1')->getId();
        $statement = $this->sut->getStatement($statementId);
        static::assertNull($statement->getAssignee());

        /** @var User $user */
        $user = $this->fixtures->getReference('testUserPlanningOffice');
        $this->sut->setAssigneeOfStatement($statement, $user);
        $statement = $this->sut->getStatement($statementId);
        static::assertEquals($user, $statement->getAssignee());

        // test "stealing" the Statement
        /** @var User $user2 */
        $user2 = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);
        $this->sut->setAssigneeOfStatement($statement, $user2);
        $statement = $this->sut->getStatement($statementId);
        static::assertEquals($user2, $statement->getAssignee());

        // test "unlock" the Statement
        $this->sut->setAssigneeOfStatement($statement);
        $statement = $this->sut->getStatement($statement->getId());
        static::assertNull($statement->getAssignee());
    }

    public function testUpdateStatement()
    {
        /** @var Statement $statement */
        $statement = $this->fixtures->getReference('testStatementAssigned6');

        $updateData = [
            'ident'          => $statement->getId(),
            'text'           => 'updated Text 1',
            'parent'         => $this->fixtures->getReference('testStatement1'),
            'user'           => $this->fixtures->getReference('testRolePlanningOffice'),
            'organisation'   => $this->fixtures->getReference('testOrgaFP'),
            'procedure'      => $this->fixtures->getReference('testProcedure2'),
            'paragraph'      => $this->fixtures->getReference('testParagraph2Version'),
            'priority'       => 'priority 1',
            'externId'       => 'externId 1',
            'internId'       => 'internId 1',
            'phase'          => 'configuration',
            'status'         => 'notSoNew',
            'sentAssessment' => true,
            'publicVerified' => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'title'          => 'updated Title 1',
            'memo'           => 'updated memo 1',
            'recommendation' => 'updated recommendation 1',
        ];

        static::assertEquals($updateData['ident'], $statement->getId());
        static::assertNotEquals($updateData['text'], $statement->getText());
        static::assertNotEquals($updateData['parent'], $statement->getParent());
        static::assertNotEquals($updateData['organisation'], $statement->getOrganisation());
        static::assertNotEquals($updateData['procedure'], $statement->getProcedure());
        static::assertNotEquals($updateData['priority'], $statement->getPriority());
        static::assertNotEquals($updateData['externId'], $statement->getExternId());
        static::assertNotEquals($updateData['internId'], $statement->getInternId());
        static::assertNotEquals($updateData['phase'], $statement->getPhase());
        static::assertNotEquals($updateData['status'], $statement->getStatus());
        static::assertNotEquals($updateData['sentAssessment'], $statement->getSentAssessment());
        static::assertNotEquals($updateData['publicVerified'], $statement->getPublicVerified());
        static::assertNotEquals($updateData['title'], $statement->getTitle());
        static::assertNotEquals($updateData['memo'], $statement->getMemo());
        static::assertNotEquals($updateData['recommendation'], $statement->getRecommendation());

        $statement->setText($updateData['text']);
        $statement->setParent($updateData['parent']);
        $statement->setOrganisation($updateData['organisation']);
        $statement->setProcedure($updateData['procedure']);
        $statement->setParagraph($updateData['paragraph']);
        $statement->setPriority($updateData['priority']);
        $statement->setExternId($updateData['externId']);
        $statement->setInternId($updateData['internId']);
        $statement->setPhase($updateData['phase']);
        $statement->setStatus($updateData['status']);
        $statement->setSentAssessment($updateData['sentAssessment']);
        $statement->setPublicVerified($updateData['publicVerified']);
        $statement->setTitle($updateData['title']);
        $statement->setMemo($updateData['memo']);
        $statement->setRecommendation($updateData['recommendation']);

        $result = $this->sut->updateStatementObject($statement);
        static::assertInstanceOf(Statement::class, $result);

        $updatedStatement = $this->sut->getStatement($result->getId());
        static::assertEquals($updatedStatement->getId(), $statement->getId());
        static::assertEquals($updatedStatement->getText(), $statement->getText());
        static::assertEquals($updatedStatement->getParent(), $statement->getParent());
        static::assertEquals($updatedStatement->getOrganisation(), $statement->getOrganisation());
        static::assertEquals($updatedStatement->getProcedure(), $statement->getProcedure());
        static::assertEquals($updatedStatement->getParagraph(), $statement->getParagraph());
        static::assertEquals($updatedStatement->getPriority(), $statement->getPriority());
        static::assertEquals($updatedStatement->getExternId(), $statement->getExternId());
        static::assertEquals($updatedStatement->getInternId(), $statement->getInternId());
        static::assertEquals($updatedStatement->getPhase(), $statement->getPhase());
        static::assertEquals($updatedStatement->getStatus(), $statement->getStatus());
        static::assertEquals($updatedStatement->getSentAssessment(), $statement->getSentAssessment());
        static::assertEquals($updatedStatement->getPublicVerified(), $statement->getPublicVerified());
        static::assertEquals($updatedStatement->getTitle(), $statement->getTitle());
        static::assertEquals($updatedStatement->getMemo(), $statement->getMemo());
        static::assertEquals($updatedStatement->getRecommendation(), $statement->getRecommendation());
    }

    public function testUpdateStatementText()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        /** @var Statement $statement */
        $statement = $this->fixtures->getReference('testStatementAssigned6');

        $updateData = [
            'r_ident'          => $statement->getId(),
            'r_text'           => 'updated Text 1',
            'r_parent'         => $this->fixtures->getReference('testStatement1')->getId(),
            'r_user'           => $this->fixtures->getReference('testRolePlanningOffice')->getId(),
            'r_organisation'   => $this->fixtures->getReference('testOrgaFP')->getId(),
            'r_procedure'      => $this->fixtures->getReference('testProcedure2')->getId(),
            'r_paragraph'      => $this->fixtures->getReference('testParagraph2Version')->getId(),
            'r_priority'       => 'priority 1',
            'r_externId'       => 'externId 1',
            'r_internId'       => 'internId 1',
            'r_phase'          => 'configuration',
            'r_status'         => 'notSoNew',
            'r_sentAssessment' => true,
            'r_publicAllowed'  => false,
            'r_title'          => 'updated Title 1',
            'r_memo'           => 'updated memo 1',
            'r_recommendation' => 'updated recommendation 1',
        ];

        static::assertEquals($updateData['r_ident'], $statement->getId());
        static::assertNotEquals($updateData['r_text'], $statement->getText());
        static::assertNotEquals($updateData['r_parent'], $statement->getParent());
        static::assertNotEquals($updateData['r_organisation'], $statement->getOrganisation());
        static::assertNotEquals($updateData['r_procedure'], $statement->getProcedure());
        static::assertNotEquals($updateData['r_paragraph'], $statement->getParagraph());
        static::assertNotEquals($updateData['r_priority'], $statement->getPriority());
        static::assertNotEquals($updateData['r_externId'], $statement->getExternId());
        static::assertNotEquals($updateData['r_internId'], $statement->getInternId());
        static::assertNotEquals($updateData['r_phase'], $statement->getPhase());
        static::assertNotEquals($updateData['r_status'], $statement->getStatus());
        static::assertNotEquals($updateData['r_sentAssessment'], $statement->getSentAssessment());
        static::assertNotEquals($updateData['r_publicAllowed'], $statement->getPublicAllowed());
        static::assertNotEquals($updateData['r_title'], $statement->getTitle());
        static::assertNotEquals($updateData['r_memo'], $statement->getMemo());
        static::assertNotEquals($updateData['r_recommendation'], $statement->getRecommendation());

        $result = $this->sut->updateStatementText($updateData);
        static::assertInstanceOf(Statement::class, $result);

        $updatedStatement = $this->sut->getStatement($result->getId());
        static::assertEquals($updateData['r_ident'], $updatedStatement->getId());
        static::assertEquals($updateData['r_text'], $updatedStatement->getText());
        static::assertEquals($updateData['r_recommendation'], $updatedStatement->getRecommendation());
        static::assertNotEquals($updateData['r_parent'], $updatedStatement->getParent());
        static::assertNotEquals($updateData['r_organisation'], $updatedStatement->getOrganisation());
        static::assertNotEquals($updateData['r_procedure'], $updatedStatement->getProcedure());
        static::assertNotEquals($updateData['r_paragraph'], $updatedStatement->getParagraph());
        static::assertNotEquals($updateData['r_priority'], $updatedStatement->getPriority());
        static::assertNotEquals($updateData['r_externId'], $updatedStatement->getExternId());
        static::assertNotEquals($updateData['r_internId'], $updatedStatement->getInternId());
        static::assertNotEquals($updateData['r_phase'], $updatedStatement->getPhase());
        static::assertNotEquals($updateData['r_status'], $updatedStatement->getStatus());
        static::assertNotEquals($updateData['r_sentAssessment'], $updatedStatement->getSentAssessment());
        static::assertNotEquals($updateData['r_publicAllowed'], $updatedStatement->getPublicAllowed());
        static::assertNotEquals($updateData['r_title'], $updatedStatement->getTitle());
        static::assertNotEquals($updateData['r_memo'], $updatedStatement->getMemo());
    }

    public function testUpdateStatementObject()
    {
        /** @var Statement $statement */
        $statement = $this->fixtures->getReference('testStatementAssigned6');
        /** @var User $user */
        $user = $this->fixtures->getReference('testUser2');
        /** @var Statement $parent */
        $parent = $this->fixtures->getReference('testStatement1');
        /** @var Orga $organisation */
        $organisation = $this->fixtures->getReference('testOrgaFP');
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');
        /** @var ParagraphVersion $paragraphVersion */
        $paragraphVersion = $this->fixtures->getReference('testParagraph3Version');
        $updatedText = 'updated Text 1';
        $updatedPriority = 'priority 1';
        $updatedExternId = 'externId 1';
        $updatedInternId = 'internId 1';
        $updatedPhase = 'configuration';
        $updatedStatus = 'notSoNew';
        $updatedSentAssessment = true;
        $updatedPublicVerified = Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED;
        $updatedTitle = 'updated Title 1';
        $updatedMemo = 'updated memo 1';
        $updatedRecommendation = 'updated recommendation 1';

        static::assertNotEquals($statement->getUser(), $user);
        static::assertNotEquals($statement->getParent(), $parent);
        static::assertNotEquals($statement->getOrganisation(), $organisation);
        static::assertNotEquals($statement->getProcedure(), $procedure);
        static::assertNotEquals($statement->getParagraph(), $paragraphVersion);
        static::assertNotEquals($statement->getText(), $updatedText);
        static::assertNotEquals($statement->getPriority(), $updatedPriority);
        static::assertNotEquals($statement->getExternId(), $updatedExternId);
        static::assertNotEquals($statement->getInternId(), $updatedInternId);
        static::assertNotEquals($statement->getPhase(), $updatedPhase);
        static::assertNotEquals($statement->getStatus(), $updatedStatus);
        static::assertNotEquals($statement->getSentAssessment(), $updatedSentAssessment);
        static::assertNotEquals($statement->getPublicVerified(), $updatedPublicVerified);
        static::assertNotEquals($statement->getTitle(), $updatedTitle);
        static::assertNotEquals($statement->getMemo(), $updatedMemo);
        static::assertNotEquals($statement->getRecommendation(), $updatedRecommendation);

        $statement->setUser($user);
        $statement->setParent($parent);
        $statement->setOrganisation($organisation);
        $statement->setProcedure($procedure);
        $statement->setParagraph($paragraphVersion);
        $statement->setText($updatedText);
        $statement->setPriority($updatedPriority);
        $statement->setExternId($updatedExternId);
        $statement->setInternId($updatedInternId);
        $statement->setPhase($updatedPhase);
        $statement->setStatus($updatedStatus);
        $statement->setSentAssessment($updatedSentAssessment);
        $statement->setPublicVerified($updatedPublicVerified);
        $statement->setTitle($updatedTitle);
        $statement->setMemo($updatedMemo);
        $statement->setRecommendation($updatedRecommendation);

        $result = $this->sut->updateStatementObject($statement);
        static::assertInstanceOf(Statement::class, $result);

        $updatedStatement = $this->sut->getStatement($statement->getId());
        static::assertEquals($updatedStatement->getUser(), $user);
        static::assertEquals($updatedStatement->getParent(), $parent);
        static::assertEquals($updatedStatement->getOrganisation(), $organisation);
        static::assertEquals($updatedStatement->getProcedure(), $procedure);
        static::assertEquals($updatedStatement->getParagraph(), $paragraphVersion);
        static::assertEquals($updatedStatement->getText(), $updatedText);
        static::assertEquals($updatedStatement->getPriority(), $updatedPriority);
        static::assertEquals($updatedStatement->getExternId(), $updatedExternId);
        // intern id only gets from original statement -> null
        //        static::assertEquals($updatedStatement->getInternId(), $updatedInternId);
        static::assertEquals($updatedStatement->getPhase(), $updatedPhase);
        static::assertEquals($updatedStatement->getStatus(), $updatedStatus);
        static::assertEquals($updatedStatement->getSentAssessment(), $updatedSentAssessment);
        static::assertEquals($updatedStatement->getPublicVerified(), $updatedPublicVerified);
        static::assertEquals($updatedStatement->getTitle(), $updatedTitle);
        static::assertEquals($updatedStatement->getMemo(), $updatedMemo);
        static::assertEquals($updatedStatement->getRecommendation(), $updatedRecommendation);
    }

    public function testGetStatementsOfProcedureAndSubmitter(): void
    {
        $testProcedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $testUser = $this->getUserReference('testUserDataInput1');

        /** @var StatementMeta[] $statementMetas */
        $statementMetas = $this->getEntries(StatementMeta::class, ['submitUId' => $testUser->getid()]);

        // only find statements which are originals and submitter == testuser:
        $statementsToBeFound = [];
        foreach ($statementMetas as $meta) {
            if ($meta->getStatement()->isOriginal()
                && $meta->getStatement()->getProcedureId() === $testProcedure->getId()) {
                $statementsToBeFound[] = $meta->getStatement();
            }
        }

        $results = $this->sut->getStatementsOfProcedureAndOrganisation($testProcedure->getId(), $testUser->getOrganisationId());
        static::assertIsArray($results);
        static::assertCount(count($statementsToBeFound), $results);

        foreach ($results as $result) {
            static::assertInstanceOf(Statement::class, $result);
            static::assertEquals($testProcedure->getId(), $result->getProcedureId());
            static::assertEquals($testUser->getId(), $result->getMeta()->getSubmitUId());
            static::assertContains($result, $statementsToBeFound);
        }
    }

    public function testAddManualStatement()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        $sessionUserId = $this->testUser->getId();
        $orgaId = $this->testUser->getOrga()->getId();
        /** @var StatementMeta[] $statementMetas */
        $statementMetas = $this->getEntries(StatementMeta::class, ['submitUId' => $sessionUserId]);
        static::assertCount(0, $statementMetas);

        // new Statement by userXY
        $data = [
            'r_title'                 => 'newTitle',
            'r_internId'              => 'id123',
            'r_text'                  => 'newtext',
            'r_ident'                 => $testProcedure->getId(),
            'r_userId'                => $this->fixtures->getReference('testUserPlanningOffice')->getId(),
            'r_organisationId'        => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
            'r_userOrganisation'      => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
            'r_elementId'             => $this->fixtures->getReference('testElement1')->getId(),
            'r_paragraphId'           => $this->fixtures->getReference('testParagraph2Version')->getId(),
            'r_phase'                 => 'participation',
            'r_role'                  => 1,
            'r_submit_type'           => 'letter',
        ];

        $createdStatement = $this->sut->newStatement($data);
        static::assertInstanceOf(Statement::class, $createdStatement);

        $results = $this->sut->getStatementsOfProcedureAndOrganisation($testProcedure->getId(), $orgaId);

        static::assertIsArray($results);
        static::assertCount(1, $results);
        $result = $results[0];
        static::assertEquals($createdStatement->getExternId(), $result['externId']);
    }

    public function testAddManualStatementSimilarStatementSubmitter()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->enablePermissions(['feature_similar_statement_submitter']);

        $similarSubmitter1 = [
            'fullName'     => 'Test Person1',
            'emailAddress' => 'person1@test.de',
        ];
        $similarSubmitter2 = [
            'fullName'     => 'Test Person2',
            'streetName'   => 'Teststraße',
            'streetNumber' => '42',
            'city'         => 'Testhausen',
            'postalCode'   => '12345',
        ];

        // new Statement by userXY
        $data = [
            'r_title'                      => 'test similar statement submitters',
            'r_internId'                   => 'id123334',
            'r_text'                       => 'test similar statement submitters',
            'r_ident'                      => $this->testProcedure->getId(),
            'r_userId'                     => $this->getUserReference('testUserPlanningOffice')->getId(),
            'r_organisationId'             => $this->getOrgaReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY)->getId(),
            'r_userOrganisation'           => $this->getOrgaReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY)->getName(),
            'r_elementId'                  => $this->getElementReference('testElement1')->getId(),
            'r_paragraphId'                => $this->fixtures->getReference('testParagraph2Version')->getId(),
            'r_phase'                      => 'participation',
            'r_role'                       => 1,
            'r_submit_type'                => 'letter',
            'r_similarStatementSubmitters' => [
                $similarSubmitter1,
                $similarSubmitter2,
            ],
        ];

        $createdStatement = $this->sut->newStatement($data);
        static::assertInstanceOf(Statement::class, $createdStatement);

        $repository = self::$container->get(StatementRepository::class);
        $copiedStatements = $repository->findBy([
            'original' => $createdStatement->getId(),
        ]);
        $similarSubmitters = $copiedStatements[0]->getSimilarStatementSubmitters();
        self::assertCount(2, $similarSubmitters);
        $createdSubmitter1 = $similarSubmitters[0];
        $createdSubmitter2 = $similarSubmitters[1];
        // Check similarSubmitter1
        self::assertInstanceOf(ProcedurePerson::class, $createdSubmitter1);
        self::assertEquals($createdStatement->getProcedureId(), $createdSubmitter1->getProcedure()->getId());
        self::assertEquals($similarSubmitter1['fullName'], $createdSubmitter1->getFullName());
        self::assertEquals(null, $createdSubmitter1->getStreetName());
        self::assertEquals(null, $createdSubmitter1->getStreetNumber());
        self::assertEquals(null, $createdSubmitter1->getCity());
        self::assertEquals(null, $createdSubmitter1->getPostalCode());
        self::assertEquals($similarSubmitter1['emailAddress'], $createdSubmitter1->getEmailAddress());
        // Check similarSubmitter2
        self::assertInstanceOf(ProcedurePerson::class, $createdSubmitter2);
        self::assertEquals($createdStatement->getProcedureId(), $createdSubmitter2->getProcedure()->getId());
        self::assertEquals($similarSubmitter2['fullName'], $createdSubmitter2->getFullName());
        self::assertEquals($similarSubmitter2['streetName'], $createdSubmitter2->getStreetName());
        self::assertEquals($similarSubmitter2['streetNumber'], $createdSubmitter2->getStreetNumber());
        self::assertEquals($similarSubmitter2['city'], $createdSubmitter2->getCity());
        self::assertEquals($similarSubmitter2['postalCode'], $createdSubmitter2->getPostalCode());
        self::assertEquals(null, $createdSubmitter2->getEmailAddress());
    }

    public function testCreateNewStatementCluster()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->getPermissionMock();
        $clusterPrefix = $this->globalConfig->getClusterPrefix();

        $statementsClaimedByCurrentUser = collect([]);
        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement2');
        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('childTestStatement2');
        /** @var Statement $statement4 */
        $statement4 = $this->fixtures->getReference('testStatementAssigned6');
        /** @var Statement $statement3 */
        $statement3 = $this->fixtures->getReference('testStatementAssigned12');
        /** @var Statement[] $statementsToCluster */
        $statementsToCluster = [$statement1, $statement2, $statement3, $statement4];
        $statementIdsToCluster = [$statement1->getId(), $statement2->getId(), $statement3->getId(), $statement4->getId()];

        // filter assigned statements -> not "clusterable"
        foreach ($statementsToCluster as $statement) {
            if ($statement->getAssignee() === $this->testUser) {
                $statementsClaimedByCurrentUser->push($statement);
            }
        }

        $claimedStatementsAlreadyClustered = 0;
        /** @var Statement $claimedStatement */
        foreach ($statementsClaimedByCurrentUser as $claimedStatement) {
            // Statements die bereits in einem Cluster sind, werde nur umgehangen.
            // Dadurch ändert sich nicht die Gesamtanzahl von Statemnts in der DB, die einem Clsuter zugewiesen sind.
            if ($claimedStatement->isInCluster()) {
                ++$claimedStatementsAlreadyClustered;
            }
        }

        $totalAmountOfStatementsBefore = $this->countEntries(Statement::class);
        /** @var Statement[] $allStatementsBefore */
        $allStatementsBefore = $this->getEntries(Statement::class);

        $totalAmountOfMemberOfClusterBefore = 0;
        foreach ($allStatementsBefore as $statement) {
            if ($statement->isInCluster()) {
                ++$totalAmountOfMemberOfClusterBefore;
            }
        }

        $totalAmountOfClusterBefore = 0;
        foreach ($allStatementsBefore as $statement) {
            if ($statement->isClusterStatement()) {
                ++$totalAmountOfClusterBefore;
            }
        }

        $clusterHeadStatement = $this->sut->createStatementCluster($statementIdsToCluster);

        static::assertInstanceOf(Statement::class, $clusterHeadStatement);

        /** @var Statement[] $totalAmountOfStatementsAfter */
        $totalAmountOfStatementsAfter = $this->countEntries(Statement::class);
        // Cluster is a special Type of an ordinary Statement, DB should have two more rows for original and assessmenttable
        static::assertEquals($totalAmountOfStatementsBefore + 2, $totalAmountOfStatementsAfter);

        /** @var Statement[] $allStatementsAfterClustering */
        $allStatementsAfterClustering = $this->getEntries(Statement::class);

        $totalAmountOfMemberOfCluster = 0;
        foreach ($allStatementsAfterClustering as $statement) {
            if ($statement->isInCluster()) {
                ++$totalAmountOfMemberOfCluster;
            }
        }

        // Only statements which are assigned to current user can be clustered and should be member of the cluster now.
        static::assertEquals(
            $totalAmountOfMemberOfClusterBefore + count($statementsClaimedByCurrentUser),
            $totalAmountOfMemberOfCluster
        );

        $totalAmountOfCluster = 0;
        foreach ($allStatementsAfterClustering as $statement) {
            if (0 < $statement->getCluster()->count()) {
                ++$totalAmountOfCluster;
            }
        }
        static::assertEquals($totalAmountOfClusterBefore + 1, $totalAmountOfCluster);

        /** @var Statement $statement */
        foreach ($statementsClaimedByCurrentUser as $statement) {
            // reload Statement
            $statement = $this->sut->getStatement($statement->getId());

            static::assertTrue($statement->isInCluster());
            // richtiges HeadStatement?
            static::assertEquals($clusterHeadStatement->getText(), $statement->getHeadStatement()->getText());
            static::assertEquals($clusterHeadStatement->getExternId(), $statement->getHeadStatement()->getExternId());
            static::assertEquals($clusterHeadStatement->getProcedureId(), $statement->getHeadStatement()->getProcedureId());
            static::assertEquals($clusterHeadStatement->getPhase(), $statement->getHeadStatement()->getPhase());
        }

        static::assertEquals($statement1->getText(), $clusterHeadStatement->getText());
        static::assertEquals($clusterPrefix.$statement1->getExternId(), $clusterHeadStatement->getExternId());
        static::assertEquals($statement1->getProcedureId(), $clusterHeadStatement->getProcedureId());
        static::assertEquals($statement1->getPhase(), $clusterHeadStatement->getPhase());

        static::assertNull($clusterHeadStatement->getHeadStatement());
        static::assertNull($clusterHeadStatement->getAssignee());

        static::assertEmpty($clusterHeadStatement->getTitle());
        static::assertNotNull($clusterHeadStatement->getParentId());

        $clusterPrefix = $this->globalConfig->getClusterPrefix();
        static::assertEquals($clusterPrefix.$statement1->getExternId(), $clusterHeadStatement->getExternId());
        static::assertEquals($statement1->getAssignee(), $clusterHeadStatement->getAssignee());
        static::assertEquals($statement1->getCounties()->toArray(), $clusterHeadStatement->getCounties()->toArray());
        static::assertEquals($statement1->getDocument(), $clusterHeadStatement->getDocument());
        static::assertEquals($statement1->getElement(), $clusterHeadStatement->getElement());
        static::assertEquals($statement1->getMapFile(), $clusterHeadStatement->getMapFile());
        static::assertEquals($statement1->getMemo(), $clusterHeadStatement->getMemo());
        static::assertEquals($statement1->getMunicipalities()->toArray(), $clusterHeadStatement->getMunicipalities()->toArray());
        static::assertEquals($statement1->getPhase(), $clusterHeadStatement->getPhase());
        static::assertEquals($statement1->getPolygon(), $clusterHeadStatement->getPolygon());
        static::assertEquals($statement1->getPriority(), $clusterHeadStatement->getPriority());
        static::assertEquals($statement1->getParagraph(), $clusterHeadStatement->getParagraph());
        static::assertEquals($statement1->getPriorityAreas()->toArray(), $clusterHeadStatement->getPriorityAreas()->toArray());
        static::assertEquals($statement1->getProcedure(), $clusterHeadStatement->getProcedure());
        static::assertEquals($statement1->getRecommendation(), $clusterHeadStatement->getRecommendation());
        static::assertEquals($statement1->getStatus(), $clusterHeadStatement->getStatus());
        static::assertEquals($statement1->getSubmitObject(), $clusterHeadStatement->getSubmitObject());
        static::assertEquals($statement1->getTags()->toArray(), $clusterHeadStatement->getTags()->toArray());
        static::assertEquals($statement1->getTopicNames(), $clusterHeadStatement->getTopicNames());
        static::assertEquals($statement1->getText(), $clusterHeadStatement->getText());
        static::assertEquals($statement1->getRepresents(), $clusterHeadStatement->getRepresents());
        static::assertEquals('email', $clusterHeadStatement->getFeedback());
        static::assertEquals($statement1->getVotePla(), $clusterHeadStatement->getVotePla());
        static::assertEquals($statement1->getVoteStk(), $clusterHeadStatement->getVoteStk());
        static::assertEquals($statement1->getFiles(), $clusterHeadStatement->getFiles());

        // todo: test without permission !
    }

    /**
     * Make it impossible to create (cluster) statements with a name considered too long.
     *
     * The current implementation throws an StatementNameTooLongException, but you may adjust this test as
     * you like (eg. when the validation is refactored to use symfony validation) as long as the name length check
     * is tested here.
     *
     * @throws Exception
     */
    public function testCreateStatementClusterWithNameTooLong()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->expectException(StatementNameTooLongException::class);

        $this->getPermissionMock();

        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement');
        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('testStatementAssigned7');

        $statementIdsToCluster = [$statement1->getId(), $statement2->getId()];
        $this->sut->setAssigneeOfStatement($statement1, $this->testUser);

        $this->sut->createStatementCluster(
            $statement1->getProcedureId(),
            $statementIdsToCluster,
            $statement1->getId(),
            str_repeat('x', 201)
        );

        self::fail('expected specific exception');
    }

    public function testCreateNewStatementClusterFromStatementWithFragments()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->getPermissionMock();

        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement');
        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('testStatementAssigned7');

        $statementIdsToCluster = [$statement1->getId(), $statement2->getId()];

        $this->sut->setAssigneeOfStatement($statement1, $this->testUser);

        $clusterHeadStatement = $this->sut->createStatementCluster($statementIdsToCluster, $statement1);

        static::assertInstanceOf(Statement::class, $clusterHeadStatement);

        static::assertEquals($statement1->getFragments()->count(), $clusterHeadStatement->getFragments()->count());
        static::assertEquals($statement1->getFragments()->first()->getText(), $clusterHeadStatement->getFragments()->first()->getText());
    }

    public function testAddStatementToCluster()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->getPermissionMock();

        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var Statement $clusterStatement */
        $clusterStatement = $this->fixtures->getReference('clusterStatement1');
        /** @var Statement $statement6 */
        $statement6 = $this->fixtures->getReference('testStatementAssigned6');
        /** @var Statement $statement7 */
        $statement7 = $this->fixtures->getReference('testStatementAssigned7');

        static::assertCount(1, $clusterStatement->getCluster());

        // check assumption:
        static::assertEquals($statement7->getId(), $clusterStatement->getCluster()[0]->getId());
        static::assertContains($statement7, $clusterStatement->getCluster());
        static::assertEquals($statement7->getHeadStatement(), $clusterStatement);
        static::assertEquals($statement7->getText(), $clusterStatement->getCluster()[0]->getText());
        static::assertEquals($statement7->getAssignee(), $user);
        static::assertNull($clusterStatement->getHeadStatement());
        static::assertEquals($clusterStatement->getAssignee(), $user);

        static::assertEquals($statement6->getAssignee(), $user);
        // add statement to cluster:
        $result = $this->sut->addStatementToCluster($clusterStatement, $statement6);
        static::assertNotFalse($result);
        static::assertInstanceOf(Statement::class, $result);

        $clusterStatement = $this->sut->getStatement($clusterStatement->getId());
        $statement6 = $this->sut->getStatement($statement6->getId());

        // check added Statement and cluster:
        static::assertCount(2, $clusterStatement->getCluster());
        static::assertNull($clusterStatement->getHeadStatement());
        static::assertEquals($clusterStatement, $statement6->getHeadStatement());

        // todo: check for not assigned!:
        // check for statement is not longer reachable in "normal way" -> should not appearing in lists and CO.
    }

    public function testDetachStatementFromCluster()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->getPermissionMock();
        /** @var Statement $clusterStatement */
        $clusterStatement = $this->fixtures->getReference('clusterStatement1');

        // to enable actions while permission is enabled:
        $clusterStatement->setAssignee($this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));

        /** @var Statement $statement7 */
        $statement7 = $this->fixtures->getReference('testStatementAssigned7');
        /** @var Statement $statement6 */
        $statement6 = $this->fixtures->getReference('testStatementAssigned6');

        $totalAmountOfStatementsBefore = $this->countEntries(Statement::class);

        // check assumption:
        static::assertEquals($statement7->getId(), $clusterStatement->getCluster()[0]->getId());
        static::assertContains($statement7, $clusterStatement->getCluster());
        static::assertEquals($statement7->getHeadStatement(), $clusterStatement);
        static::assertEquals($statement7->getText(), $clusterStatement->getCluster()[0]->getText());
        static::assertNull($clusterStatement->getHeadStatement());

        // add statement to cluster:
        $successful = $this->sut->addStatementToCluster($clusterStatement, $statement6);
        static::assertNotFalse($successful);
        static::assertInstanceOf(Statement::class, $successful);

        $clusterStatement = $this->sut->getStatement($clusterStatement->getId());
        $statement6 = $this->sut->getStatement($statement6->getId());

        // check added Statement and cluster:
        static::assertCount(2, $clusterStatement->getCluster());
        static::assertNull($clusterStatement->getHeadStatement());
        static::assertEquals($clusterStatement, $statement6->getHeadStatement());

        // remove 1 of 2 clustersElements
        $successful = $this->sut->detachStatementFromCluster($statement7);
        static::assertTrue($successful);

        $clusterStatement = $this->sut->getStatement($clusterStatement->getId());
        $statement7 = $this->sut->getStatement($statement7->getId());

        static::assertCount(1, $clusterStatement->getCluster());
        static::assertNull($clusterStatement->getHeadStatement());
        static::assertEmpty($statement7->getCluster());
        static::assertNull($statement7->getHeadStatement());
        static::assertFalse($statement7->isInCluster());

        // removing the last element of the cluster should result in deleting the cluster/headStatement itself
        $successful = $this->sut->detachStatementFromCluster($statement6);
        static::assertTrue($successful);

        $clusterStatement = $this->sut->getStatement($clusterStatement->getId());
        $statement6 = $this->sut->getStatement($statement6->getId());

        static::assertNull($clusterStatement);
        static::assertEmpty($statement6->getCluster());
        static::assertNull($statement6->getHeadStatement());
        static::assertFalse($statement6->isInCluster());

        static::assertEquals($totalAmountOfStatementsBefore - 1,
            $this->countEntries(Statement::class));

        // todo: test without permission?
    }

    public function testUpdateClusterMember()
    {
        self::markSkippedForCIIntervention();
        // unable to get featrues, user, and sessionId via mocking,
        // because these are getting via the "get()", but with different parameters. No idea how to mock different results.

        // should be blocked because statement is member of cluster
        /** @var Statement $memberOfCluster */
        $memberOfCluster = $this->fixtures->getReference('testStatementAssigned7');
        static::assertTrue($memberOfCluster->isInCluster());
        $oldText = $memberOfCluster->getText();

        $memberOfCluster->setText('some new Text');
        $updatedStatement = $this->sut->updateStatementObject($memberOfCluster);
        static::assertFalse($updatedStatement);

        $updatedStatement = $this->sut->getStatement($memberOfCluster->getId());
        static::assertEquals($oldText, $updatedStatement->getText());
    }

    public function testUpdateStatementCluster()
    {
        /** @var Statement $memberOfCluster */
        $memberOfCluster = $this->fixtures->getReference('testStatementAssigned7');
        $oldText = $memberOfCluster->getText();
        $headStatement = $memberOfCluster->getHeadStatement();

        $newText = 'updated Text of HeadStatement';
        $headStatement->setText($newText);
        $numberOfStatementsInCluster = $headStatement->getNumberOfStatementsInCluster();
        $updatedStatement = $this->sut->updateStatementObject($headStatement);
        static::assertInstanceOf(Statement::class, $updatedStatement);

        $memberOfCluster = $this->sut->getStatement($memberOfCluster->getId());
        $updatedStatement = $this->sut->getStatement($headStatement->getId());

        static::assertEquals($oldText, $memberOfCluster->getText());
        static::assertEquals($newText, $updatedStatement->getText());

        static::assertEquals($updatedStatement->getNumberOfStatementsInCluster(), $numberOfStatementsInCluster);
        static::assertEquals($updatedStatement, $memberOfCluster->getHeadStatement());
    }

    public function testResolveStatementCluster()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        // all statements of the cluster will be "normal" statements again
        $allStatementsBefore = $this->getEntries(Statement::class);
        $totalAmountOfStatementsBefore = count($allStatementsBefore);
        $totalAmountOfClusterBefore = 0;
        $totalAmountOfStatementsInClusterBefore = 0;
        /** @var Statement $statement */
        foreach ($allStatementsBefore as $statement) {
            if ($statement->isInCluster()) {
                ++$totalAmountOfStatementsInClusterBefore;
            }
            if ($statement->isClusterStatement()) {
                ++$totalAmountOfClusterBefore;
            }
        }

        /** @var Statement $headStatement */
        $headStatement = $this->fixtures->getReference('clusterStatement1');
        $headStatementId = $headStatement->getId();
        $statementsOfCluster = collect($headStatement->getCluster());

        // execute resolving:
        $successful = $this->sut->resolveCluster($headStatement);

        $allStatementsAfter = $this->getEntries(Statement::class);

        $totalAmountOfClusterAfter = 0;
        $totalAmountOfStatementsInClusterAfter = 0;
        /** @var Statement $statement */
        foreach ($allStatementsAfter as $statement) {
            if ($statement->isInCluster()) {
                ++$totalAmountOfStatementsInClusterAfter;
            }
            if ($statement->isClusterStatement()) {
                ++$totalAmountOfClusterAfter;
            }
        }
        static::assertEquals($totalAmountOfStatementsBefore - 1, $this->countEntries(Statement::class));

        static::assertNull($this->sut->getStatement($headStatementId));
        static::assertTrue($successful);
        static::assertEquals(
            $totalAmountOfStatementsInClusterBefore - $statementsOfCluster->count(),
            $totalAmountOfStatementsInClusterAfter);

        static::assertEquals($totalAmountOfClusterBefore - 1, $totalAmountOfClusterAfter);

        foreach ($statementsOfCluster as $statement) {
            static::assertNull($statement->getHeadStatement());
        }
    }

    public function testGetHeadStatementIdsOfStatements()
    {
        /** @var Statement $cluster1 */
        $cluster1 = $this->fixtures->getReference('clusterStatement1');
        /** @var Statement $cluster2 */
        $cluster2 = $this->fixtures->getReference('clusterStatement2');
        /** @var Statement $noCluster */
        $noCluster = $this->fixtures->getReference('testStatementAssigned7');
        $toFilter = [$cluster1->getId(), $cluster2->getId(), $noCluster->getId()];

        $result = $this->sut->getHeadStatementIdsOfStatementIds($toFilter);

        static::assertCount(2, $result);

        foreach ($result as $statementId) {
            $statement = $this->sut->getStatement($statementId);
            static::assertTrue($statement->isClusterStatement());
        }
    }

    public function testIsHeadStatementCluster()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->getPermissionMock();

        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement2');
        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('childTestStatement2');
        /** @var Statement $statement4 */
        $statement4 = $this->fixtures->getReference('testStatementAssigned6');
        /** @var Statement $statement3 */
        $statement3 = $this->fixtures->getReference('testStatementAssigned7');

        $statementIdsToCluster = [
            $statement1->getId(),
            $statement2->getId(),
            $statement3->getId(),
            $statement4->getId(),
        ];

        $clusterHeadStatement = $this->sut->createStatementCluster(
            $statementIdsToCluster
        );

        static::assertTrue($clusterHeadStatement->isClusterStatement());
        $originalStatement = $clusterHeadStatement->getParent();
        static::assertCount(1, $originalStatement->getChildren());
        static::assertTrue($originalStatement->isClusterStatement());
    }

    public function testIsNotHeadStatementCluster()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');
        $sessionUserId = $this->testUser->getId();
        /** @var StatementMeta[] $statementMetas */
        $statementMetas = $this->getEntries(StatementMeta::class, ['submitUId' => $sessionUserId]);
        static::assertCount(0, $statementMetas);

        // new Statement by userXY
        $data = [
            'r_title'          => 'newTitle',
            'r_externId'       => 'id123',
            'r_text'           => 'newtext',
            'r_ident'          => $testProcedure->getId(),
            'r_userId'         => $this->fixtures->getReference('testUserPlanningOffice')->getId(),
            'r_organisationId' => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
            'r_elementId'      => $this->fixtures->getReference('testElement1')->getId(),
            'r_paragraphId'    => $this->fixtures->getReference('testParagraph2Version')->getId(),
            'r_phase'          => 'participation',
            'r_submitType'     => '$statement2',
            'r_role'           => 1,
            'r_submit_type'    => 'letter',
        ];

        $createdOriginalStatement = $this->sut->newStatement($data);

        static::assertFalse($createdOriginalStatement->isClusterStatement());
        static::assertFalse($createdOriginalStatement->getChildren()->first()->isClusterStatement());
    }

    public function testAddChild()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement1');
        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('testStatement2');
        static::assertNull($statement1->getParent());
        static::assertEmpty($statement1->getChildren());
        static::assertNull($statement2->getParent());
        static::assertEmpty($statement2->getChildren());

        // execute function to test:
        $statement1->addChild($statement2);

        // update and load entities:
        $this->sut->updateStatementObject($statement1);
        $statement1 = $this->sut->getStatement($statement1->getId());
        $statement2 = $this->sut->getStatement($statement2->getId());

        // assertions
        static::assertCount(1, $statement1->getChildren());
        static::assertEquals($statement2, $statement1->getChildren()[0]);
        static::assertNotNull($statement2->getParent());
        static::assertEquals($statement1, $statement2->getParent());
    }

    public function testRemoveChild()
    {
        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement1');
        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('testStatement2');
        // prepare:
        $statement1->addChild($statement2);
        $this->sut->updateStatementObject($statement1);
        $statement1 = $this->sut->getStatement($statement1->getId());
        $statement2 = $this->sut->getStatement($statement2->getId());
        static::assertCount(1, $statement1->getChildren());
        static::assertEquals($statement2, $statement1->getChildren()[0]);
        static::assertNotNull($statement2->getParent());
        static::assertEquals($statement1, $statement2->getParent());

        // execute function to test:
        $statement1->removeChild($statement2);

        // update and load entities:
        $this->sut->updateStatementObject($statement1);
        $statement1 = $this->sut->getStatement($statement1->getId());
        $statement2 = $this->sut->getStatement($statement2->getId());

        // assertions:
        static::assertNull($statement2->getParent());
        static::assertEmpty($statement1->getChildren());
    }

    public function testSetParent()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement1');

        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('testStatement2');

        static::assertNull($statement1->getParent());
        static::assertEmpty($statement1->getChildren());
        static::assertNull($statement2->getParent());
        static::assertEmpty($statement2->getChildren());

        // execute function to test:
        $statement2->setParent($statement1);

        // update and load entities:
        $this->sut->updateStatementObject($statement1);
        $statement1 = $this->sut->getStatement($statement1->getId());
        $statement2 = $this->sut->getStatement($statement2->getId());

        // assertions
        static::assertCount(1, $statement1->getChildren());
        static::assertEquals($statement2, $statement1->getChildren()[0]);
        static::assertNotNull($statement2->getParent());
        static::assertEquals($statement1, $statement2->getParent());
    }

    public function testSetChildren()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        /** @var Statement $statement1 */
        $statement1 = $this->fixtures->getReference('testStatement1');
        /** @var Statement $statement2 */
        $statement2 = $this->fixtures->getReference('testStatement2');
        /** @var Statement $statement3 */
        $statement3 = $this->fixtures->getReference('testStatement');

        static::assertNull($statement1->getParent());
        static::assertEmpty($statement1->getChildren());
        static::assertNull($statement2->getParent());
        static::assertEmpty($statement2->getChildren());
        static::assertNull($statement3->getParent());
        static::assertEmpty($statement3->getChildren());

        // execute function to test:
        $statement1->setChildren([$statement2, $statement3]);

        // update and load entities:
        $this->sut->updateStatementObject($statement1);
        $statement1 = $this->sut->getStatement($statement1->getId());
        $statement2 = $this->sut->getStatement($statement2->getId());
        $statement3 = $this->sut->getStatement($statement3->getId());

        // assertions
        static::assertCount(2, $statement1->getChildren());
        static::assertContains($statement2, $statement1->getChildren());
        static::assertContains($statement3, $statement1->getChildren());

        static::assertNotNull($statement2->getParent());
        static::assertNotNull($statement3->getParent());
        static::assertEquals($statement1, $statement2->getParent());
        static::assertEquals($statement1, $statement3->getParent());
    }

    public function testAssignCluster()
    {
        /** @var Statement $cluster1 */
        $cluster1 = $this->fixtures->getReference('clusterStatement1');
        $testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // reset assigment
        $this->sut->setAssigneeOfStatement($cluster1);

        $updatedCluster1 = $this->sut->getStatement($cluster1->getId());

        static::assertNull($updatedCluster1->getAssignee());
        $elements = $updatedCluster1->getCluster();
        /** @var Statement $element */
        foreach ($elements as $element) {
            static::assertNull($element->getAssignee());
        }

        // set assigment to testuser
        $this->sut->setAssigneeOfStatement($updatedCluster1, $testUser);

        static::assertEquals($testUser, $updatedCluster1->getAssignee());
        $elements = $updatedCluster1->getCluster();
        /** @var Statement $element */
        foreach ($elements as $element) {
            static::assertEquals($testUser, $element->getAssignee());
        }
    }

    public function testClusterStatementWithUnassignedFragments()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $statementWithFragments */
        $statementWithFragments = $this->fixtures->getReference('testStatement');
        /** @var Statement $anotherStatement */
        $anotherStatement = $this->fixtures->getReference('testStatement1');

        /** @var StatementFragment[] $fragmentsOfStatement */
        $fragmentsOfStatement = $statementWithFragments->getFragments();

        static::assertNotEmpty($fragmentsOfStatement);
        static::assertNull($statementWithFragments->getAssignee());
        static::assertNull($anotherStatement->getAssignee());
        static::assertEmpty($anotherStatement->getFragments());

        $result = $this->sut->createStatementCluster([$anotherStatement, $statementWithFragments]);
        static::assertFalse($result);
    }

    public function testDenyCreateClusterWithFragmentsOnReviewer()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->expectException(Exception::class);

        /** @var Statement $statementWithFragments */
        $statementWithFragments = $this->fixtures->getReference('testStatement');
        $statementWithFragments->setAssignee($this->testUser);
        /** @var Statement $anotherStatement */
        $anotherStatement = $this->fixtures->getReference('testStatement1');
        $anotherStatement->setAssignee($this->testUser);

        static::assertEquals($this->testUser, $statementWithFragments->getAssignee());
        static::assertEquals($this->testUser, $anotherStatement->getAssignee());
        static::assertNotEmpty($statementWithFragments->getFragments());
        // means: that Fragment is assigned to reviewer: -> clustering this statement should be denied
        static::assertInstanceOf(Department::class, $statementWithFragments->getFragments()[0]->getDepartment());
        static::assertSame($statementWithFragments->getProcedureId(), $anotherStatement->getProcedureId());

        $this->sut->createStatementCluster(
            $anotherStatement->getProcedureId(),
            [$anotherStatement->getId(), $statementWithFragments->getId()],
            $anotherStatement->getId()
        );
    }

    public function testGetStatementFragmentsStatementES()
    {
        self::markSkippedForCIElasticsearchUnavailable();
    }

    public function testHeadStatementOnCopyClusterToProcedure()
    {
        /** @var Statement $clusterStatement */
        $clusterStatement = $this->fixtures->getReference('clusterStatement1');
        /** @var Procedure $targetProcedure */
        $sourceProcedure = $clusterStatement->getProcedure();
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        $amountOfMemberBefore = $clusterStatement->getCluster()->count();
        $amountOfClusterBefore = $this->countEntries(Statement::class, ['clusterStatement' => true]);
        $amountOfClusterInSourceProcedureBefore = $this->countEntries(Statement::class, ['clusterStatement' => true, 'procedure' => $sourceProcedure->getId()]);
        $amountOfClusterInTargetProcedureBefore = $this->countEntries(Statement::class, ['clusterStatement' => true, 'procedure' => $targetProcedure->getId()]);

        static::assertNotEquals($sourceProcedure->getId(), $targetProcedure->getId());
        static::assertTrue($clusterStatement->isClusterStatement());
        $copiedCluster = $this->sut->copyStatementToProcedure($clusterStatement, $targetProcedure);
        static::assertInstanceOf(Statement::class, $copiedCluster);
        static::assertEquals($targetProcedure->getId(), $copiedCluster->getProcedureId());
        static::assertTrue($copiedCluster->isClusterStatement());
        static::assertCount($amountOfMemberBefore, $copiedCluster->getCluster());
        static::assertNotFalse(strpos($copiedCluster->getExternId(), 'G'));

        $clusterStatement = $this->find(Statement::class, $clusterStatement->getId());
        static::assertInstanceOf(Statement::class, $clusterStatement);
        static::assertNotEquals($clusterStatement->getId(), $copiedCluster->getId());
        static::assertNotEquals($clusterStatement->getProcedureId(), $copiedCluster->getProcedureId());

        $amountOfClusterAfter = $this->countEntries(Statement::class, ['clusterStatement' => true]);
        // originalSTN + STN will be marked as cluster statements
        static::assertEquals($amountOfClusterBefore + 2, $amountOfClusterAfter);

        $amountOfClusterInSourceProcedureAfter = $this->countEntries(Statement::class, ['clusterStatement' => true, 'procedure' => $sourceProcedure->getId()]);
        $amountOfClusterInTargetProcedureAfter = $this->countEntries(Statement::class, ['clusterStatement' => true, 'procedure' => $targetProcedure->getId()]);
        static::assertEquals($amountOfClusterInSourceProcedureBefore, $amountOfClusterInSourceProcedureAfter);
        // originalSTN + STN will be marked as cluster statements
        static::assertEquals($amountOfClusterInTargetProcedureBefore + 2, $amountOfClusterInTargetProcedureAfter);
    }

    public function testNumberOfStatementsOnCopyClusterToProcedure()
    {
        /** @var Statement $clusterStatement */
        $clusterStatement = $this->fixtures->getReference('clusterStatement1');
        $numberOfMemberToCopy = $clusterStatement->getCluster()->count();
        $sourceProcedure = $clusterStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        static::assertTrue($clusterStatement->isClusterStatement());

        /** @var Statement[] $statements */
        $statements = $this->getEntries(Statement::class);
        $totalAmountOfMemberBefore = 0;
        foreach ($statements as $statement) {
            if ($statement->isInCluster()) {
                ++$totalAmountOfMemberBefore;
            }
        }

        /** @var Statement[] $statements */
        $statements = $this->getEntries(Statement::class, ['procedure' => $targetProcedure->getId()]);
        $amountOfMemberOfTargetProcedureBefore = 0;
        foreach ($statements as $statement) {
            if ($statement->isInCluster()) {
                ++$amountOfMemberOfTargetProcedureBefore;
            }
        }

        /** @var Statement[] $statements */
        $statements = $this->getEntries(Statement::class, ['procedure' => $sourceProcedure->getId()]);
        $amountOfMemberOfSourceProcedureBefore = 0;
        foreach ($statements as $statement) {
            if ($statement->isInCluster()) {
                ++$amountOfMemberOfSourceProcedureBefore;
            }
        }

        $this->sut->copyStatementToProcedure($clusterStatement, $targetProcedure);

        $totalAmountOfMemberAfter = 0;
        /** @var Statement[] $allStatements */
        $allStatements = $this->getEntries(Statement::class);
        foreach ($allStatements as $statement) {
            if ($statement->isInCluster()) {
                ++$totalAmountOfMemberAfter;
            }
        }
        static::assertEquals($totalAmountOfMemberBefore + $numberOfMemberToCopy, $totalAmountOfMemberAfter);

        /** @var Statement[] $statements */
        $statements = $this->getEntries(Statement::class, ['procedure' => $targetProcedure->getId()]);
        $amountOfMemberOfTargetProcedureAfter = 0;
        foreach ($statements as $statement) {
            if ($statement->isInCluster()) {
                ++$amountOfMemberOfTargetProcedureAfter;
            }
        }
        static::assertEquals($amountOfMemberOfTargetProcedureBefore + $numberOfMemberToCopy, $amountOfMemberOfTargetProcedureAfter);

        /** @var Statement[] $statements */
        $statements = $this->getEntries(Statement::class, ['procedure' => $sourceProcedure->getId()]);
        $amountOfMemberOfSourceProcedureAfter = 0;
        foreach ($statements as $statement) {
            if ($statement->isInCluster()) {
                ++$amountOfMemberOfSourceProcedureAfter;
            }
        }
        static::assertEquals($amountOfMemberOfSourceProcedureBefore, $amountOfMemberOfSourceProcedureAfter);
    }

    public function testMemberStatementsOnCopyClusterToProcedure()
    {
        /** @var Statement $clusterStatement */
        $clusterStatement = $this->fixtures->getReference('clusterStatement1');
        $sourceProcedure = $clusterStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        static::assertTrue($clusterStatement->isClusterStatement());
        $movedCluster = $this->sut->copyStatementToProcedure($clusterStatement, $targetProcedure);

        foreach ($movedCluster->getCluster() as $member) {
            static::assertInstanceOf(Statement::class, $member);
            static::assertEquals($targetProcedure->getId(), $member->getProcedureId());
            static::assertNotNull($member->getHeadStatement());
            static::assertEquals($movedCluster->getId(), $member->getHeadStatement()->getId());
            static::assertEquals($targetProcedure->getId(), $member->getProcedureId());
        }

        foreach ($clusterStatement->getCluster() as $member) {
            static::assertInstanceOf(Statement::class, $member);
            static::assertEquals($sourceProcedure->getId(), $member->getProcedureId());
            static::assertNotNull($member->getHeadStatement());
            static::assertEquals($clusterStatement->getId(), $member->getHeadStatement()->getId());
            static::assertEquals($sourceProcedure->getId(), $member->getProcedureId());
        }
    }

    // ------------------------------------Hilfsmethoden------------------------------------

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getServiceStorageMock()
    {
        $mock = $this->getMockBuilder(DraftStatementHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->any())
            ->method('newHandler')
            ->willReturn(['ident' => 'fakeIdent', 'number' => 'fakeNumber']);
        $mock->expects($this->any())
            ->method('releaseHandler')
            ->willReturn(true);
        $mock->expects($this->any())
            ->method('submitHandler')
            ->willReturn([]);

        return $mock;
    }

    /**
     * @return PHPUnit_Framework_MockObject_MockObject
     */
    protected function getFileServiceMock()
    {
        $fileInfo = $this->getFileInfoTagImport();
        $mock = $this->getMockBuilder(FileService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->any())
            ->method('getFileInfo')
            ->willReturn($fileInfo);
        return $mock->expects($this->any())
            ->method('getFileContentStream')
            ->willReturn(fopen($fileInfo->getAbsolutePath(), 'rb'));

    }

    /**
     * @param bool $county
     * @param bool $municipality
     * @param bool $priorityArea
     * @param bool $fragmentAdd
     * @param bool $cluster
     */
    protected function getSessionMock($county = true,
        $municipality = true,
        $priorityArea = true,
        $fragmentAdd = true,
        $cluster = true): Session
    {
        $constructorArgs = [new MockArraySessionStorage()];
        $sessionMock = $this->getMockBuilder(Session::class)->setConstructorArgs($constructorArgs)->getMock();

        $permissions['field_statement_county']['enabled'] = $county;
        $permissions['field_statement_municipality']['enabled'] = $municipality;
        $permissions['field_statement_priority_area']['enabled'] = $priorityArea;
        $permissions['feature_statements_fragment_add']['enabled'] = $fragmentAdd;
        $permissions['feature_statement_cluster']['enabled'] = $cluster;

        $sessionMock->expects($this->any())
            ->method('has')
            ->with('demosplanUser')
            ->willReturn(true);

        $sessionMock->expects($this->any())
            ->method('get')
            ->with('sessionId')
            ->willReturn('justAIdForReportEntry');

        $sessionMock->expects($this->any())
            ->method('get')
            ->with($this->logicalOr('permissions', 'demosplanUser'))
            ->willReturnCallback(
                function ($parameter) use ($permissions) {
                    switch ($parameter) {
                        case 'permissions':
                            return $permissions;
                            break;
                        case 'demosplanUser':
                            return $this->fixtures->getReference(
                                LoadUserData::TEST_USER_GUEST
                            );
                            break;
                        default:
                            return null;
                            break;
                    }
                }
            );

        return $sessionMock;
    }

    protected function setMocks()
    {
        $mock = $this->getServiceStorageMock();
        $this->sut->setDraftStatementHandler($mock);

        /** @var FileService $fileServiceMock */
        $fileServiceMock = $this->getFileServiceMock();
        $this->sut->setFileService($fileServiceMock);
    }

    /**
     * @return Permissions
     */
    protected function getPermissionMock()
    {
        $permissionMock = $this->getMockBuilder(
            Permissions::class
        )->disableOriginalConstructor()->getMock();

        $permissionMock->expects($this->any())
            ->method('hasPermission')
            ->with('feature_statement_assignment')
            ->willReturn(true);

        /* @var Permissions $permissionMock */
        $this->sut->setPermissions($permissionMock);

        return $permissionMock;
    }

    /**
     * DataProvider.
     */
    public function getFragmentUpdateVoteAdviceAndAssignmentAtTheSameTimeData(): array
    {
        $departmentId = $this->fixtures->getReference('testDepartment')->getId();

        return [
            // vote & reviewer
            [['r_reviewer' => $departmentId, 'r_vote_advice' => 'full']],
            // reset reviewer
            [['r_reviewer' => '', 'r_vote_advice' => 'full']],
            // vote only
            [['r_vote_advice' => 'full']],
            // reviewer only
            [['r_reviewer' => $departmentId]],
        ];
    }

    /**
     * @throws Exception
     */
    public function testFragmentUpdateVoteAdviceAndAssignmentAtTheSameTime(/* $providerData */)
    {
        self::markSkippedForCIIntervention();

        $permissions = $this->sut->getPermissions();
        $permissions->enablePermissions([
            'field_statement_county',
            'field_statement_municipality',
            'field_statement_priority_area',
            'feature_statements_fragment_add_reviewer',
        ]);
        $this->sut->setPermissions($permissions);

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentFilled1');
        static::assertNull($fragment->getDepartmentId());

        // to ensure update will not fail because of assignment
        $this->sut->setAssigneeOfStatementFragment($fragment, $this->testUser);
        $this->logIn($this->testUser);

        $isVoteAdviceGiven = array_key_exists('r_vote_advice', $providerData);
        $isReviewerGiven = array_key_exists('r_reviewer', $providerData);

        // to ensure difference
        if ($isVoteAdviceGiven) {
            static::assertNotEquals($providerData['r_vote_advice'], $fragment->getVoteAdvice());
        }

        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(), $providerData, false);

        // successfully updated?
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);

        // actually assertions
        if ($isReviewerGiven && $isVoteAdviceGiven) {
            // only the voteAdvice should be saved
            static::assertEquals($providerData['r_vote_advice'], $fragment->getVoteAdvice());
            //            assertNotEquals do not differentiate between '' and null
            static::assertNotSame($providerData['r_reviewer'], $fragment->getDepartmentId());
        }
        if (!$isReviewerGiven && $isVoteAdviceGiven) {
            static::assertEquals($providerData['r_vote_advice'], $fragment->getVoteAdvice());
        }

        if ($isReviewerGiven && !$isVoteAdviceGiven) {
            static::assertEquals($providerData['r_reviewer'], $fragment->getDepartmentId());
        }
    }

    public function testStateOfStatementFragment()
    {
        $this->enablePermissions(['feature_statements_fragment_edit', 'field_fragment_status']);
        /** @var StatementFragment|Proxy $fragment */
        $fragment = StatementFragmentFactory::createOne();
        $fragmentId = $fragment->getId();

        $updatedFragment = $this->sut->updateStatementFragment($fragmentId, ['status' => 'read'], false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        $updatedFragment = $this->sut->getStatementFragment($fragmentId);
        static::assertEquals('read', $updatedFragment->getStatus());
    }

    public function testFragmentStateInitial()
    {
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssigned4');
        $statementId = $fragment->getStatementId();
        $procedureId = $fragment->getProcedureId();

        $fragmentData = [
            'r_text'      => 'neuer Text eines frisch erstellen Datensatzes.',
            'statementId' => $statementId,
            'procedureId' => $procedureId,
        ];

        $newFragment = $this->sut->createStatementFragment($fragmentData);
        static::assertEquals('fragment.status.new', $newFragment->getStatus());
        static::assertEquals($fragmentData['r_text'], $newFragment->getText());
        static::assertEquals($fragmentData['statementId'], $newFragment->getStatementId());
    }

    public function testFragmentStateSetReviewerInitial()
    {
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssigned4');
        $departmentId = $this->fixtures->getReference('testDepartment')->getId();
        $statementId = $fragment->getStatementId();
        $procedureId = $fragment->getProcedureId();

        $fragmentData = [
            'r_text'      => 'neuer Text eines frisch erstellen Datensatzes (incl. zuweisung zu einem department).',
            'statementId' => $statementId,
            'procedureId' => $procedureId,
            'r_reviewer'  => $departmentId,
        ];

        $newFragment = $this->sut->createStatementFragment($fragmentData);
        static::assertEquals('fragment.status.assignedToFB', $newFragment->getStatus());
        static::assertEquals($fragmentData['r_text'], $newFragment->getText());
        static::assertEquals($fragmentData['statementId'], $newFragment->getStatementId());
        static::assertEquals($fragmentData['r_reviewer'], $newFragment->getDepartmentId());
    }

    public function testFragmentStateSetReviewerOfNewState()
    {
        self::markSkippedForCIIntervention();

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssigned4');
        $departmentId = $this->fixtures->getReference('testDepartment')->getId();
        static::assertEquals('fragment.status.new', $fragment->getStatus());

        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            ['r_reviewer' => $departmentId], false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());
    }

    public function testFragmentStateSetReviewerOfVerifiedState()
    {
        self::markSkippedForCIIntervention();

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentWithVerifiedState');
        $departmentId = $this->fixtures->getReference('testDepartment')->getId();
        static::assertEquals('fragment.status.verified', $fragment->getStatus());
        static::assertNull($fragment->getArchivedOrgaName());

        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            ['r_reviewer' => $departmentId], false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());
    }

    public function testFragmentStateRemoveReviewer()
    {
        self::markSkippedForCIIntervention();

        // prepare:
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssignedToDepartment');
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());

        /** @var Department $newDepartment */
        $department = $this->fixtures->getReference('testDepartment');
        static::assertEquals($department, $fragment->getDepartment());
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());

        // test:
        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            ['r_reviewer' => null], false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.new', $fragment->getStatus());
    }

    public function testFragmentStateReSetReviewer()
    {
        self::markSkippedForCIIntervention();

        // prepare:
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssignedToDepartment');
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());

        /** @var Department $department */
        $department = $this->fixtures->getReference('testDepartment');
        static::assertEquals($department->getId(), $fragment->getDepartmentId());
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());

        // test:
        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            ['r_reviewer' => $department->getId()], false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());
    }

    public function testFragmentStateUpdateComplete()
    {
        self::markSkippedForCIIntervention();

        $this->getSessionMock();
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssignedToDepartment');
        // Avoid sending mail
        $fragment->getProcedure()->getOrga()->setEmail2(null);
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());

        // Bearbeitung des Datensatzes abschliessen von Fachbehörde
        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            [
                'r_notify'         => 'on',
                'r_orgaName'       => 'myOrgaName',
                'r_departmentName' => 'myDepartmentName',
            ],
            true
        );
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.assignedBackFromFB', $fragment->getStatus());
    }

    public function testFragmentStateUnsetVerifyAfterReviewerUpdateComplete()
    {
        self::markSkippedForCIIntervention();

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssignedWithArchivedOrga');
        static::assertEquals('fragment.status.verified', $fragment->getStatus());

        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            [
                'r_reviewer' => null,
                'state'      => null,
            ],
            false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.assignedBackFromFB', $fragment->getStatus());
    }

    public function testFragmentStateUnsetVerifyBeforeReviewerUpdateComplete()
    {
        self::markSkippedForCIIntervention();

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentWithVerifiedStateWithoutArchivedOrga');
        static::assertEquals('fragment.status.verified', $fragment->getStatus());

        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            [
                'r_reviewer' => null,
                'state'      => null,
            ],
            false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.new', $fragment->getStatus());
    }

    public function testFragmentStateSetVerified()
    {
        // prepare:
        $this->enablePermissions(['feature_statements_fragment_edit', 'field_fragment_status']);
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragmentAssignedToDepartment');
        static::assertEquals('fragment.status.assignedToFB', $fragment->getStatus());

        // test:
        $updatedFragment = $this->sut->updateStatementFragment($fragment->getId(),
            ['status' => 'on'], false);
        static::assertInstanceOf(StatementFragment::class, $updatedFragment);
        static::assertEquals('fragment.status.verified', $fragment->getStatus());
    }

    public function testGetStatementVotes()
    {
        $testUser2 = $this->fixtures->getReference('testUser2');
        $testUser3 = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);

        $testStatementVote1 = $this->fixtures->getReference('testStatementVote1');
        $testStatementVote2 = $this->fixtures->getReference('testStatementVote3');

        $statementVotes = $this->sut->getStatementVotes($testUser2->getId());

        static::assertCount(2, $statementVotes);

        foreach ($statementVotes as $sv) {
            static::assertEquals($sv->getUser(), $testUser2);
        }

        $statementVotes = $this->sut->getStatementVotes($testUser2->getId(), true, false);

        static::assertCount(1, $statementVotes);

        foreach ($statementVotes as $sv) {
            static::assertEquals($sv->getUser(), $testUser2);
        }

        $statementVotes = $this->sut->getStatementVotes($testUser2->getId(), false, false);

        static::assertCount(1, $statementVotes);

        foreach ($statementVotes as $sv) {
            static::assertEquals($sv->getUser(), $testUser2);
        }

        $statementVotes = $this->sut->getStatementVotes($testUser3->getId());

        static::assertCount(1, $statementVotes);

        foreach ($statementVotes as $sv) {
            static::assertEquals($sv->getUser(), $testUser3);
        }
    }

    public function testGetStatementsByVotes()
    {
        self::markSkippedForCIIntervention();
        // there is some weird entity not found error in here

        $testUser2 = $this->fixtures->getReference('testUser2');
        $testUser3 = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);

        $testStatementVote1 = $this->fixtures->getReference('testStatementVote1');
        $testStatementVote2 = $this->fixtures->getReference('testStatementVote2');

        $testStatement1 = $this->fixtures->getReference('testStatement1');
        $testStatement2 = $this->fixtures->getReference('testStatement2');

        $votes = [$testStatementVote1, $testStatementVote2];

        $statements = $this->sut->getStatementsByVotes($votes);

        static::assertCount(2, $statements);
        static::assertContains($testStatement1, $statements);
        static::assertContains($testStatement2, $statements);
    }

    public function testGetStatementsByUserVotes()
    {
        $testUser2 = $this->fixtures->getReference('testUser2');
        $testUser3 = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);

        $testStatement1 = $this->fixtures->getReference('testStatement1');
        $testStatement2 = $this->fixtures->getReference('testStatement2');

        $statements = $this->sut->getStatementsByUserVotes($testUser2->getId());

        static::assertCount(2, $statements);
        static::assertContains($testStatement1, $statements);
        static::assertContains($testStatement2, $statements);

        $statements = $this->sut->getStatementsByUserVotes($testUser3->getId());

        static::assertCount(1, $statements);
        static::assertContains($testStatement1, $statements);
    }

    public function testGetClusterOfProcedure()
    {
        self::markSkippedForCIIntervention();

        $procedureId = $this->testProcedure->getId();
        $referenceClusters = collect([]);
        /** @var Statement[] $unfilteredReferenceClusters */
        $unfilteredReferenceClusters = $this->getEntries(
            Statement::class,
            [
                'procedure'        => $procedureId,
                'deleted'          => false,
                'clusterStatement' => true,
            ]
        );

        foreach ($unfilteredReferenceClusters as $referenceCluster) {
            if (!$referenceCluster->isOriginal()) {
                $referenceClusters->push($referenceCluster);
            }
        }

        $clustersOfProcedure = $this->sut->getClustersOfProcedure($this->testProcedure->getId());
        static::assertCount($referenceClusters->count(), $clustersOfProcedure);
        static::assertEquals($referenceClusters->toArray(), $clustersOfProcedure);
    }

    public function testAddParagraphToFragment()
    {
        self::markSkippedForCIIntervention();

        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragment1');
        $this->sut->setAssigneeOfStatementFragment($fragment, $this->testUser);

        /** @var Paragraph $paragraphToSet */
        $paragraphToSet = $this->fixtures->getReference('testParagraph1');

        /** @var Elements $elementToSet */
        $elementToSet = $this->fixtures->getReference('testElement1');
        $relatedElementOfParagraph = $paragraphToSet->getElement();
        static::assertEquals($elementToSet, $relatedElementOfParagraph);

        // testParagraph1 is part of testElement1
        $updateData = [
            'ident'       => $fragment->getId(),
            'r_paragraph' => $paragraphToSet,
            'r_element'   => $elementToSet,
        ];

        static::assertEquals($updateData['ident'], $fragment->getId());
        static::assertNotEquals($updateData['text'], $fragment->getText());
        static::assertNotEquals($updateData['status'], $fragment->getStatus());
        static::assertNotEquals($updateData['r_paragraph'], $fragment->getParagraph());
        static::assertNotEquals($updateData['r_element'], $fragment->getParagraph());

        $result = $this->sut->updateStatementFragment($updateData['ident'], $updateData, false);
        static::assertInstanceOf(StatementFragment::class, $result);
        $updatedFragment = $this->sut->getStatementFragment($result->getId());

        static::assertEquals($updatedFragment->getId(), $fragment->getId());
        static::assertEquals($updatedFragment->getText(), $fragment->getText());
        static::assertEquals($updatedFragment->getProcedure(), $fragment->getProcedure());
        static::assertEquals($updatedFragment->getStatus(), $fragment->getStatus());

        static::assertInstanceOf(ParagraphVersion::class, $fragment->getParagraph());
        static::assertEquals($updatedFragment->getParagraph()->getText(), $fragment->getParagraph()->getText());
        static::assertEquals($updatedFragment->getElement(), $relatedElementOfParagraph);
    }

    public function testRemoveParagraphFromFragment()
    {
        /** @var StatementFragment $fragment */
        $fragment = $this->fixtures->getReference('testStatementFragment1');
        $this->sut->setAssigneeOfStatementFragment($fragment, $this->testUser);
        $this->logIn($this->testUser);

        // ------------ Prepare Fragment with paragraph and element: ------------
        /** @var Paragraph $paragraphToSet */
        $paragraphToSet = $this->fixtures->getReference('testParagraph1');

        /** @var Elements $elementToSet */
        $elementToSet = $this->fixtures->getReference('testElement1');

        $relatedElementOfParagraph = $paragraphToSet->getElement();
        static::assertEquals($elementToSet, $relatedElementOfParagraph);

        $updateData = [
            'ident'       => $fragment->getId(),
            'r_paragraph' => $paragraphToSet,
            'r_element'   => $elementToSet,
        ];
        $result = $this->sut->updateStatementFragment($updateData['ident'], $updateData, false);
        static::assertInstanceOf(StatementFragment::class, $result);
        $updatedFragment = $this->sut->getStatementFragment($result->getId());

        static::assertEquals($updatedFragment->getId(), $fragment->getId());
        static::assertEquals($updatedFragment->getText(), $fragment->getText());
        static::assertEquals($updatedFragment->getProcedure(), $fragment->getProcedure());
        static::assertEquals($updatedFragment->getStatus(), $fragment->getStatus());

        static::assertInstanceOf(ParagraphVersion::class, $fragment->getParagraph());
        static::assertEquals($updatedFragment->getParagraph()->getText(), $fragment->getParagraph()->getText());
        static::assertEquals($updatedFragment->getElement(), $relatedElementOfParagraph);
        // --------------------------------------------------------------------

        // testParagraph1 is part of testElement1
        $updateData = [
            'ident'       => $fragment->getId(),
            'text'        => 'updated Text 78789784568656489654',
            'status'      => 'notSoNew',
            'r_paragraph' => '',
            'r_element'   => '',
        ];

        static::assertEquals($updateData['ident'], $fragment->getId());
        static::assertNotEquals($updateData['text'], $fragment->getText());
        static::assertNotEquals($updateData['status'], $fragment->getStatus());

        $result = $this->sut->updateStatementFragment($updateData['ident'], $updateData, false);
        static::assertInstanceOf(StatementFragment::class, $result);
        $updatedFragment = $this->sut->getStatementFragment($result->getId());

        static::assertEquals($updatedFragment->getId(), $fragment->getId());
        static::assertEquals($updatedFragment->getText(), $fragment->getText());
        static::assertEquals($updatedFragment->getProcedure(), $fragment->getProcedure());
        static::assertEquals($updatedFragment->getStatus(), $fragment->getStatus());

        static::assertNull($fragment->getParagraph());
        static::assertNull($fragment->getElement());
    }

    public function testGetNameOfMunicipality()
    {
        /** @var Municipality $municipality1 */
        $municipality1 = $this->fixtures->getReference('testMunicipality1');
        /** @var Municipality $municipality3 */
        $municipality3 = $this->fixtures->getReference('testMunicipality3');

        static::assertNull($municipality1->getOfficialMunicipalityKey());

        static::assertNotNull($municipality3->getOfficialMunicipalityKey());
        $lastFiveCharactersOfName = substr($municipality3->getName(), -5);
        static::assertEquals($municipality3->getOfficialMunicipalityKey(), $lastFiveCharactersOfName);
        static::assertStringEndsWith($lastFiveCharactersOfName, ' - '.$municipality3->getName());
    }

    public function testGetStatementByInternIdAndProcedureId()
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->fixtures->getReference('testProcedure');

        $foundByInternId = $this->sut->getStatementByInternIdAndProcedureId(2222, $testProcedure->getId());
        static::assertInstanceOf(Statement::class, $foundByInternId);
    }

    public function testMoveClusterToProcedure()
    {
        self::markSkippedForCIIntervention();

        // low priority: not implemented yet

        // get procedure with statements
        // select single statement
        // $this->sut->moveStatementToProcedure($statement, $procedure)

        // check: parentcluster, statements, fragments, originalSN, procedureneu, procedureold,
        // still have same fragments, related fragments in new procedure?
        // original STN in oldprocedure?
    }

    public function testGetAllowedVoteValues()
    {
        self::markSkippedForCIIntervention();

        $expectedVoteValues = [
            'acknowledge',
            'followed',
            'following',
            'full',
            'no',
            'noFollow',
            'partial',
            'workInProgress',
        ];

        $newStatement = new Statement();
        static::assertEquals($expectedVoteValues, $newStatement->getAllowedVoteValues());
    }

    public function testDenyUpdateStatementBecauseLockedByCluster()
    {
        self::markSkippedForCIIntervention();

        /** @var Statement $clusteredStatement */
        $clusteredStatement = $this->fixtures->getReference('testStatementAssigned7');
        static::assertTrue($clusteredStatement->isInCluster());

        $clusteredStatement->setHeadStatement(null);
        $clusteredStatement->setText('blöblöbla');

        $result = $this->sut->updateStatementObject($clusteredStatement);
        static::assertNotInstanceOf(Statement::class, $result);
        static::assertFalse($result);
    }

    public function testClaimStatementCluster()
    {
        self::markSkippedForCIIntervention();

        /** @var Statement $testHeadStatement3 */
        $testHeadStatement3 = $this->fixtures->getReference('clusterStatement3');
        static::assertNull($testHeadStatement3->getAssignee());

        $successful = $this->sut->setAssigneeOfStatement($testHeadStatement3, $this->testUser);
        static::assertTrue($successful);

        static::assertEquals($testHeadStatement3->getAssignee(), $this->testUser);
    }

    public function testCopyEmptyStatementToProcedure()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $sourceProcedure = $testStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');

        // check setup: targetProcedure != sourceProcedure
        static::assertNotEquals($targetProcedure->getId(), $sourceProcedure->getId());

        $copiedStatement = $this->sut->copyStatementToProcedure($testStatement, $targetProcedure);

        static::assertEquals($targetProcedure->getId(), $copiedStatement->getProcedureId());
        static::assertEquals($targetProcedure->getId(), $copiedStatement->getOriginal()->getProcedureId());
        static::assertEquals($targetProcedure->getId(), $copiedStatement->getElement()->getProcedure()->getId());
        // will not work because of testdata?:
        //        static::assertContains($copiedStatement, $copiedStatement->getOriginal()->getChildren());
        //        static::assertNotContains($testStatement, $copiedStatement->getOriginal()->getChildren());
        //        static::assertContains($testStatement, $testStatement->getOriginal()->getChildren());
        //        static::assertNotContains($copiedStatement, $testStatement->getOriginal()->getChildren());
    }

    public function testAmountOfStatementsOnCopyEmptyStatementToProcedure()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $sourceProcedure = $testStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');

        // check setup: targetProcedure != sourceProcedure
        static::assertNotEquals($targetProcedure->getId(), $sourceProcedure->getId());

        $amountOfStatementsBefore = $this->countEntries(Statement::class);
        $amountOfOriginalStatementsBefore = $this->countEntries(Statement::class, ['original' => null]);

        $this->sut->copyStatementToProcedure($testStatement, $targetProcedure);

        $amountOfStatementsAfter = $this->countEntries(Statement::class);
        $amountOfOriginalStatementsAfter = $this->countEntries(Statement::class, ['original' => null]);

        static::assertEquals($amountOfOriginalStatementsBefore + 1, $amountOfOriginalStatementsAfter);
        static::assertEquals($amountOfStatementsBefore + 2, $amountOfStatementsAfter);
    }

    public function testReportEntryOnCopyStatementToProcedure()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $sourceProcedure = $testStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');

        // check setup: targetProcedure != sourceProcedure
        static::assertNotEquals($targetProcedure->getId(), $sourceProcedure->getId());

        $amountOfReportsOfSourceProcedureBefore = $this->countEntries(ReportEntry::class, ['identifier' => $sourceProcedure->getId()]);
        $amountOfReportsOfTargetProcedureBefore = $this->countEntries(ReportEntry::class, ['identifier' => $targetProcedure->getId()]);

        $this->sut->copyStatementToProcedure($testStatement, $targetProcedure);

        $amountOfReportsOfSourceProcedureAfter = $this->countEntries(ReportEntry::class, ['identifier' => $sourceProcedure->getId()]);
        $amountOfReportsOfTargetProcedureAfter = $this->countEntries(ReportEntry::class, ['identifier' => $targetProcedure->getId()]);

        static::assertEquals($amountOfReportsOfSourceProcedureBefore + 1, $amountOfReportsOfSourceProcedureAfter);
        static::assertEquals($amountOfReportsOfTargetProcedureBefore + 1, $amountOfReportsOfTargetProcedureAfter);
    }

    public function testCopyFragmentsOnCopyStatementToProcedure()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $sourceProcedure = $testStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        static::assertNotEmpty($testStatement->getFragments());
        $amountOfFragmentOfSourceStatement = $testStatement->getFragments()->count();

        // check setup: targetProcedure != sourceProcedure
        static::assertNotEquals($targetProcedure->getId(), $sourceProcedure->getId());

        $copiedStatement = $this->sut->copyStatementToProcedure($testStatement, $targetProcedure);

        static::assertCount($amountOfFragmentOfSourceStatement, $copiedStatement->getFragments());
    }

    public function testCopyStatementWithFileToProcedure()
    {
        $fileService = self::$container->get(FileService::class);
        // add file first
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/test.txt', 'file1');
        $fileService->saveTemporaryFile($cacheDir.'/test.txt', 'Testfilename');
        $fileString = $fileService->getFileString();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatementWithFile');
        $statementService = self::$container->get(StatementService::class);
        $sourceProcedure = $testStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        $testStatement = $statementService->addFilesToStatementObject([$fileString], $testStatement);
        static::assertNotEmpty($testStatement->getFiles());
        // check setup: targetProcedure != sourceProcedure
        static::assertNotEquals($targetProcedure->getId(), $sourceProcedure->getId());

        $copiedStatement = $this->sut->copyStatementToProcedure($testStatement, $targetProcedure);

        static::assertNotEmpty($copiedStatement->getFiles());
        static::assertNotEquals(
            $statementService->getStatement($testStatement->getId())->getFiles(),
            $copiedStatement->getFiles()
        );
        // new file has reference to target procedure
        $newFile = $fileService->getFileFromFileString($copiedStatement->getFiles()[0]);
        static::assertEquals($targetProcedure->getId(), $newFile->getProcedure()->getId());
    }

    public function testCopyStatementWithMapFileToProcedure()
    {
        $fileService = self::$container->get(FileService::class);
        // add file first
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/test.txt', 'file1');
        $fileService->saveTemporaryFile($cacheDir.'/test.txt', 'Testfilename');
        $fileString = $fileService->getFileString();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatementWithFile');
        $statementService = self::$container->get(StatementService::class);
        $sourceProcedure = $testStatement->getProcedure();
        /** @var Procedure $targetProcedure */
        $targetProcedure = $this->fixtures->getReference('testProcedure2');
        $testStatement->setMapFile($fileString);
        $testStatement = $statementService->updateStatementFromObject($testStatement);
        static::assertNotEmpty($testStatement->getMapFile());
        // check setup: targetProcedure != sourceProcedure
        static::assertNotEquals($targetProcedure->getId(), $sourceProcedure->getId());

        $copiedStatement = $this->sut->copyStatementToProcedure($testStatement, $targetProcedure);

        static::assertNotEmpty($copiedStatement->getMapFile());
        static::assertNotEquals(
            $statementService->getStatement($testStatement->getId())->getMapFile(),
            $copiedStatement->getMapFile()
        );

        // new map file has reference to target procedure
        $newFile = $fileService->getFileFromFileString($copiedStatement->getMapFile());
        static::assertEquals($targetProcedure->getId(), $newFile->getProcedure()->getId());
    }

    private function getFileInfoTagImport(): FileInfo
    {
        return new FileInfo(
            hash: 'someHash',
            fileName: 'tagTopics.csv',
            fileSize: 12345,
            contentType: 'any/thing',
            path: __DIR__ . '/res/tagTopics.csv',
            absolutePath: __DIR__ . '/res/tagTopics.csv',
            procedure: null
        );
    }
}
