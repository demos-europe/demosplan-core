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
use demosplan\DemosPlanCoreBundle\Entity\SearchIndexTask;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Logic\SearchIndexTaskService;
use Tests\Base\FunctionalTestCase;

/**
 * Teste SearchIndexTaskServiceTest.
 *
 * @group UnitTest
 */
class SearchIndexTaskServiceTest extends FunctionalTestCase
{
    /**
     * @var SearchIndexTaskService
     */
    protected $sut;
    /**
     * @var \demosplan\DemosPlanCoreBundle\Repository\SearchIndexTaskRepository
     */
    protected $searchIndexTaskRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(SearchIndexTaskService::class);
        $this->searchIndexTaskRepository = self::$container->get('doctrine')->getRepository(SearchIndexTask::class);
    }

    public function testIndexSearchTask()
    {
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findAll());
        $this->sut->refreshIndex();
        self::assertCount(0, $this->searchIndexTaskRepository->findAll());
    }

    public function testIndexSearchTaskStatementOnly()
    {
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findAll());

        $this->sut->refreshIndex(Statement::class);

        self::assertCount(0, $this->searchIndexTaskRepository->findBy(['entity' => Statement::class]));
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findBy(['entity' => StatementFragment::class]));
    }

    public function testIndexSearchTaskStatementFragmentOnly()
    {
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findAll());

        $this->sut->refreshIndex(StatementFragment::class);

        self::assertCount(0, $this->searchIndexTaskRepository->findBy(['entity' => StatementFragment::class]));
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findBy(['entity' => Statement::class]));
    }

    public function testIndexSearchTaskNotExistingEntity()
    {
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findAll());

        $this->sut->refreshIndex('NotExistingEntity');

        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findBy(['entity' => Statement::class]));
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findBy(['entity' => StatementFragment::class]));
    }

    public function testIndexSearchTaskNotExistingEntityCleanUp()
    {
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findAll());
        $this->sut->addIndexTask('NotExistingEntity', 'Never mind');
        self::assertGreaterThan(0, $this->searchIndexTaskRepository->findBy(['processing' => false]));
        $this->sut->refreshIndex();
        self::assertCount(0, $this->searchIndexTaskRepository->findAll());
    }

    public function testAddSearchTaskStatement()
    {
        $initialTasks = $this->searchIndexTaskRepository->findAll();
        $this->sut->addIndexTask(Statement::class, $this->getReference('testStatement1')->getId());
        $addedStatement = $this->searchIndexTaskRepository->findAll();
        self::assertCount(count($initialTasks) + 1, $addedStatement);
    }

    public function testAddSearchTaskStatementFragment()
    {
        $initialTasks = $this->searchIndexTaskRepository->findAll();
        $this->sut->addIndexTask(StatementFragment::class, $this->getReference('testStatementFragment1')->getId());
        $addedStatementFragment = $this->searchIndexTaskRepository->findAll();
        self::assertCount(count($initialTasks) + 1, $addedStatementFragment);
    }

    public function testAddSearchTaskNotExistingEntity()
    {
        $initialTasks = $this->searchIndexTaskRepository->findAll();
        $this->sut->addIndexTask('NotExistingEntity', 'Never mind');
        $addedStatementFragment = $this->searchIndexTaskRepository->findAll();
        self::assertCount(count($initialTasks) + 1, $addedStatementFragment);
    }

    public function testDeleteSearchTaskStatement()
    {
        $initialTasks = $this->searchIndexTaskRepository->findAll();
        $this->sut->deleteFromIndexTask(Statement::class, $this->getReference('testStatement1')->getId());
        $addedStatement = $this->searchIndexTaskRepository->findAll();
        self::assertCount(count($initialTasks), $addedStatement);
    }

    public function testDeleteSearchTaskStatementFragment()
    {
        $initialTasks = $this->searchIndexTaskRepository->findAll();
        $this->sut->deleteFromIndexTask(StatementFragment::class, $this->getReference('testStatementFragment1')->getId());
        $addedStatementFragment = $this->searchIndexTaskRepository->findAll();
        self::assertCount(count($initialTasks), $addedStatementFragment);
    }

    public function testDeleteSearchTaskNotExistingEntity()
    {
        $initialTasks = $this->searchIndexTaskRepository->findAll();
        $this->sut->deleteFromIndexTask('NotExistingEntity', 'Never mind');
        $addedStatementFragment = $this->searchIndexTaskRepository->findAll();
        self::assertCount(count($initialTasks), $addedStatementFragment);
    }

    public function testHasUserPendingSearchTasks()
    {
        /** @var \demosplan\DemosPlanCoreBundle\Entity\User\User $testUser */
        $testUser = $this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($testUser);

        $items = $this->sut->hasUserPendingSearchTasks(null);
        self::assertTrue($items);
        $items = $this->sut->hasUserPendingSearchTasks($testUser->getId());
        self::assertFalse($items);
        $this->sut->addIndexTask('NotExistingEntity', 'Never mind');
        $items = $this->sut->hasUserPendingSearchTasks($testUser->getId());
        self::assertTrue($items);
        $this->sut->refreshIndex();
        $items = $this->sut->hasUserPendingSearchTasks(null);
        self::assertFalse($items);
        $items = $this->sut->hasUserPendingSearchTasks($testUser->getId());
        self::assertFalse($items);
    }
}
