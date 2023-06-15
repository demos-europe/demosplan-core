<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class EntityContentChangeServiceTest extends FunctionalTestCase
{
    /** @var EntityContentChangeService */
    protected $sut;

    /** @var StatementService */
    protected $statementService;

    /**
     * @var Session
     */
    protected $mockSession;

    /**
     * @var User
     */
    private $testUser;
    /**
     * @var EntityHelper
     */
    private $entityHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(EntityContentChangeService::class);
        $this->statementService = $this->getContainer()->get(StatementService::class);
        $this->entityHelper = $this->getContainer()->get(EntityHelper::class);

        $this->testUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($this->testUser);
        $this->enablePermissions(['feature_statement_content_changes_save']);
    }

    public function testSaveEntityContentChange(): void
    {
        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement');
        $testStatement->setText('adsfasfasfasf');

        $contentChangeDiff = $this->sut->calculateChanges($testStatement, Statement::class);

        $result = $this->sut->maybeCreateEntityContentChangeEntry(
            $testStatement,
            'text',
            $contentChangeDiff['text'],
            $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY),
            new DateTime()
        );

        // have to do, because of doctrine proxy object
        $expectedSimplifyClassName =
            substr(Statement::class, strrpos(Statement::class, '\\') + 1);
        $actualSimplifyClassName =
            substr($result->getEntityType(), strrpos($result->getEntityType(), '\\') + 1);

        static::assertSame($expectedSimplifyClassName, $actualSimplifyClassName);
        static::assertInstanceOf(EntityContentChange::class, $result);
        static::assertSame('text', $result->getEntityField());
    }

    public function testGetHistoryOfEntity(): void
    {
        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement');

        $fieldName = 'memo';
        $historyOfStatement = $this->sut->getChangesByEntityId($testStatement->getId(), [$fieldName]);

        $amountOfEntities = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => Statement::class, 'entityId' => $testStatement->getId(), 'entityField' => $fieldName]);

        static::assertIsArray($historyOfStatement);
        foreach ($historyOfStatement as $entityContentChange) {
            static::assertInstanceOf(EntityContentChange::class, $entityContentChange);
        }
        static::assertCount($amountOfEntities, $historyOfStatement);
    }

    public function testGetEntireHistoryOfEntity(): void
    {
        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement');

        $amountOfEntities = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => Statement::class, 'entityId' => $testStatement->getId()]);

        $historyOfStatement = $this->sut->getChangesByEntityId($testStatement->getId());
        static::assertIsArray($historyOfStatement);
        foreach ($historyOfStatement as $entityContentChange) {
            static::assertInstanceOf(EntityContentChange::class, $entityContentChange);
        }
        static::assertCount($amountOfEntities, $historyOfStatement);
    }

    public function testCreateEntityContentChangeEntryOnUpdateStatementArray(): void
    {
        $testStatement = $this->getStatementReference('testStatementAssigned6');
        $newStatementMemo = 'new Memo on Statement';

        static::assertFalse($testStatement->isOriginal());
        static::assertFalse($testStatement->isPlaceholder());
        static::assertFalse($testStatement->isClusterStatement());
        static::assertFalse($testStatement->isDeleted());

        $statementUpdateArray = [
            'ident' => $testStatement->getId(),
            'memo'  => $newStatementMemo,
        ];
        $updatedStatement = $this->statementService->updateStatement($statementUpdateArray, true);
        static::assertInstanceOf(Statement::class, $updatedStatement);

        $updatedStatement = $this->statementService->getStatement($updatedStatement->getId());
        static::assertSame($newStatementMemo, $updatedStatement->getMemo());

        $changesOfStatement =
            $this->sut->getChangesByEntityId($updatedStatement->getId());
        static::assertIsArray($changesOfStatement);
        static::assertCount(1, $changesOfStatement);
        static::assertInstanceOf(EntityContentChange::class, $changesOfStatement[0]);

        /** @var EntityContentChange $changeOfStatement */
        $changeOfStatement = $changesOfStatement[0];
        static::assertSame(Statement::class, $changeOfStatement->getEntityType());
        static::assertSame($updatedStatement->getId(), $changeOfStatement->getEntityId());
        static::assertSame('memo', $changeOfStatement->getEntityField());
        static::assertSame($this->testUser->getId(), $changeOfStatement->getUserId());
    }

    public function testCreateEntityContentChangeEntryOnUpdateStatementObject(): void
    {
        $testStatement = $this->getStatementReference('testStatementAssigned6');
        $this->logIn($this->testUser);
        $newStatementMemo = 'new Memo on Statement';

        static::assertFalse($testStatement->isOriginal());
        static::assertFalse($testStatement->isPlaceholder());
        static::assertFalse($testStatement->isClusterStatement());
        static::assertFalse($testStatement->isDeleted());

        $testStatement->setMemo($newStatementMemo);
        $updatedStatement = $this->statementService->updateStatementFromObject($testStatement, true);
        static::assertInstanceOf(Statement::class, $updatedStatement);

        $updatedStatement = $this->statementService->getStatement($updatedStatement->getId());
        static::assertSame($newStatementMemo, $updatedStatement->getMemo());

        $changesOfStatement =
            $this->sut->getChangesByEntityId($updatedStatement->getId());
        static::assertIsArray($changesOfStatement);
        static::assertCount(1, $changesOfStatement);
        static::assertInstanceOf(EntityContentChange::class, $changesOfStatement[0]);

        /** @var EntityContentChange $changeOfStatement */
        $changeOfStatement = $changesOfStatement[0];
        static::assertSame(Statement::class, $changeOfStatement->getEntityType());
        static::assertSame($updatedStatement->getId(), $changeOfStatement->getEntityId());
        static::assertSame('memo', $changeOfStatement->getEntityField());
        static::assertSame($this->testUser->getId(), $changeOfStatement->getUserId());
    }

    /**
     * Testing if content changes are detected and working.
     * Cases:
     *  - object update with content change, using field memo → expect no change detected
     *  - array update with content change, using field memo  → expect change detected.
     *
     * Also mock logger to check if logging is working.
     * Always expect Statement instance as return value if it successfully updated the entry.
     * Else it must be false.
     */
    public function testUpdateStatementWithContentChanges(): void
    {
        self::markSkippedForCIIntervention();
        // Need to implement this. But first unit tests have to work again.

        /** @var Statement $statement */
        $statement = $this->getReference('testStatement');
        $statementArray = $this->entityHelper->toArray($statement);

        $this->assertArrayHasKey('ident', $statementArray);
        $this->assertArrayHasKey('memo', $statementArray);

        $this->assertEquals($statement->getMemo(), $statementArray['memo']);

        $mockBuilder = $this->getMockBuilder(Logger::class);
        $mockBuilder->disableOriginalConstructor();

        // Somehow need a way to check if this message has been logged
        $logger = $mockBuilder->getMock();
        $logger->method('info')->with('Could not determine content changes for statement because of object structure.');

        // ->will(function($message) {
//        $testCase::assertEquals('Could not determine content changes for statement because of object structure.', $message);
//        });

        $updatedStatementObject = null;

        // update array. expect content change to be detected. checking by requesting entity contentChange.
        $statementArray['memo'] = 'somethingElseThanBeforeInArrayStructure';
        $updatedStatementObject = $this->statementService->updateStatement($statementArray);
        $this->assertInstanceOf(Statement::class, $updatedStatementObject);
        $this->assertEquals($updatedStatementObject->getMemo(), 'somethingElseThanBeforeInArrayStructure');
        /** @var EntityContentChange[] $contentChange */
        $contentChange = $this->sut->getChangesByEntityId($updatedStatementObject->getId());
        $lastChange = $contentChange[count($contentChange) - 1];
        $this->assertEquals($lastChange->getEntityField(), 'memo');

        // Reset $updatedStatementObject
        $updatedStatementObject = null;

        // update object. set logger to expect no content change detected
        $statement->setMemo('somethingElseThanBefore');
        // set logger to check if we get log entry → see mock
        $this->statementService->setLogger($logger);
        $updatedStatementObject = $this->statementService->updateStatement($statement);
        $this->assertInstanceOf(Statement::class, $updatedStatementObject);
        $this->assertEquals($updatedStatementObject->getMemo(), 'somethingElseThanBefore');
    }
}
