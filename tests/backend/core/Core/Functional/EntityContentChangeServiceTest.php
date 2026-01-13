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
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentBulkEditorService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class EntityContentChangeServiceTest extends FunctionalTestCase
{
    /** @var EntityContentChangeService */
    protected $sut;

    /** @var StatementService */
    protected $statementService;

    /** @var SegmentBulkEditorService */
    protected $segmentBulkEditService;

    /** @var SegmentHandler */
    protected $segmentHandler;

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
        $this->segmentBulkEditService = $this->getContainer()->get(SegmentBulkEditorService::class);
        $this->segmentHandler = $this->getContainer()->get(SegmentHandler::class);
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

        $entries = $this->sut->createEntityContentChangeEntries(
            $testStatement,
            $contentChangeDiff,
            false,
            new DateTime()
        );
        $result = $entries[0];

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
        // using AAA should erase the problem of amount of results are depending of the order of fixtures
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
     * Testing the history of the custom field value change on bulk update of segments.
     */
    public function testEntityContentChangeEntryOnUpdateCustomFieldValue(): void
    {
        $procedure = ProcedureFactory::createOne();
        $segments = SegmentFactory::createMany(2, ['procedure' => $procedure, 'assignee' => $this->testUser]);
        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1')->create();
        $customField2 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color2')->create();

        $customField1Option1 = $customField1->getConfiguration()->getOptions()[0];
        $customField2Option3 = $customField2->getConfiguration()->getOptions()[2];

        $preUpdateContentChangeEntriesCount = $this->countEntries(EntityContentChange::class);

        $segments[0] = $segments[0]->_real();
        $segments[1] = $segments[1]->_real();
        $resultSegments = [];
        /** @var Segment[] $segments */
        $segments = $this->segmentBulkEditService->updateSegments(
            $segments,
            [],
            [],
            $this->testUser,
            null,
            [
                ['id' => $customField1->getId(), 'value' => $customField1Option1->getId()],
                ['id' => $customField2->getId(), 'value' => $customField2Option3->getId()],
            ]
        );

        $resultSegments = [...$resultSegments, ...$segments];
        $methodCallTime = new DateTime();
        $this->segmentHandler->updateObjects($resultSegments, $methodCallTime);

        self::assertCount($preUpdateContentChangeEntriesCount + 4, $this->getEntries(EntityContentChange::class));

        // check history of segment1
        /** @var EntityContentChange[] $historyOfSegment1 */
        $historyOfSegment1 = $this->getEntries(
            EntityContentChange::class,
            ['entityType' => Segment::class, 'entityId' => $segments[0]->getId()]
        );
        self::assertCount(2, $historyOfSegment1);
        self::assertInstanceOf(EntityContentChange::class, $historyOfSegment1[0]);
        self::assertInstanceOf(EntityContentChange::class, $historyOfSegment1[1]);

        $newValuesOfHistoryOfSegment1 = [];
        foreach ($historyOfSegment1 as $entityContentChange) {
            // this is necessary because of the order of the entries in the history is variable (sort by createdDate)
            $newValuesOfHistoryOfSegment1[] = Json::decodeToArray($entityContentChange->getContentChange())[0][0]['new']['lines'][0];
        }

        $selectedOptionCustomField1Id = $segments[0]->getCustomFields()->findById($customField1->getId())->getValue();
        $selectedOptionCustomField1Label = $customField1->getConfiguration()->getCustomOptionValueById($selectedOptionCustomField1Id)->getLabel();
        self::assertEquals('', Json::decodeToArray($historyOfSegment1[0]->getContentChange())[0][0]['old']['lines'][0]);
        self::assertContains(
            $selectedOptionCustomField1Label,
            $newValuesOfHistoryOfSegment1
        );

        $selectedOptionCustomField2Id = $segments[0]->getCustomFields()->findById($customField2->getId())->getValue();
        $selectedOptionCustomField2Label = $customField2->getConfiguration()->getCustomOptionValueById($selectedOptionCustomField2Id)->getLabel();
        self::assertEquals('', Json::decodeToArray($historyOfSegment1[1]->getContentChange())[0][0]['old']['lines'][0]);
        self::assertContains(
            $selectedOptionCustomField2Label,
            $newValuesOfHistoryOfSegment1
        );

        // check history of segment1
        /** @var EntityContentChange[] $historyOfSegment2 */
        $historyOfSegment2 = $this->getEntries(
            EntityContentChange::class,
            ['entityType' => Segment::class, 'entityId' => $segments[1]->getId()]
        );
        self::assertCount(2, $historyOfSegment2);
        self::assertInstanceOf(EntityContentChange::class, $historyOfSegment2[0]);
        self::assertInstanceOf(EntityContentChange::class, $historyOfSegment2[1]);

        $newValuesOfHistoryOfSegment2 = [];
        foreach ($historyOfSegment2 as $entityContentChange) {
            // this is necessary because of the order of the entries in the history is variable (sort by createdDate)
            $newValuesOfHistoryOfSegment2[] = Json::decodeToArray($entityContentChange->getContentChange())[0][0]['new']['lines'][0];
        }

        $selectedOptionCustomField1Id = $segments[1]->getCustomFields()->findById($customField1->getId())->getValue();
        $selectedOptionCustomField1Label = $customField1->getConfiguration()->getCustomOptionValueById($selectedOptionCustomField1Id)->getLabel();
        self::assertEquals('', Json::decodeToArray($historyOfSegment2[0]->getContentChange())[0][0]['old']['lines'][0]);
        self::assertContains(
            $selectedOptionCustomField1Label,
            $newValuesOfHistoryOfSegment2
        );

        $selectedOptionCustomField2Id = $segments[1]->getCustomFields()->findById($customField2->getId())->getValue();
        $selectedOptionCustomField2Label = $customField2->getConfiguration()->getCustomOptionValueById($selectedOptionCustomField2Id)->getLabel();
        self::assertEquals('', Json::decodeToArray($historyOfSegment2[1]->getContentChange())[0][0]['old']['lines'][0]);
        self::assertContains(
            $selectedOptionCustomField2Label,
            $newValuesOfHistoryOfSegment2
        );
    }

    /**
     * @throws InvalidDataException
     * @throws JsonException
     */
    public function testCalculateChangesWithEntity(): void
    {
        $originalText = 'Original text content';
        $originalMemo = 'Original memo content';

        /** @var Statement $testStatement */
        $testStatement = StatementFactory::createOne([
            'text' => $originalText,
            'memo' => $originalMemo,
        ])->_real();

        $testStatement->setText('Updated text content');
        $testStatement->setMemo('Updated memo content');

        $changes = $this->sut->calculateChanges($testStatement, Statement::class);

        static::assertIsArray($changes);

        static::assertArrayHasKey('text', $changes);
        $oldText = $this->getPreUpdateValueOfContentChange('text', $changes);
        $newText = $this->getPostUpdateValueOfContentChange('text', $changes);

        static::assertArrayHasKey('memo', $changes);
        $oldMemo = $this->getPreUpdateValueOfContentChange('memo', $changes);
        $newMemo = $this->getPostUpdateValueOfContentChange('memo', $changes);

        static::assertStringContainsString($originalText, $oldText);
        static::assertStringContainsString('Updated text content', $newText);
        static::assertStringContainsString($originalMemo, $oldMemo);
        static::assertStringContainsString('Updated memo content', $newMemo);
    }

    /**
     * @throws InvalidDataException
     * @throws JsonException
     */
    public function testCalculateChangesWithArray(): void
    {
        $originalText = 'Original text content for array test';
        $originalMemo = 'Original memo content for array test';

        /** @var Statement $testStatement */
        $testStatement = StatementFactory::createOne([
            'text' => $originalText,
            'memo' => $originalMemo,
        ])->_real();

        $updateData = [
            'ident' => $testStatement->getId(),
            'text'  => 'Updated text via array',
            'memo'  => 'Updated memo via array',
        ];

        $changes = $this->sut->calculateChanges($updateData, Statement::class);

        static::assertIsArray($changes);
        static::assertArrayHasKey('text', $changes);
        static::assertArrayHasKey('memo', $changes);
        static::assertIsString($changes['text']);
        static::assertIsString($changes['memo']);

        static::assertArrayHasKey('text', $changes);
        $oldText = $this->getPreUpdateValueOfContentChange('text', $changes);
        $newText = $this->getPostUpdateValueOfContentChange('text', $changes);

        static::assertArrayHasKey('memo', $changes);
        $oldMemo = $this->getPreUpdateValueOfContentChange('memo', $changes);
        $newMemo = $this->getPostUpdateValueOfContentChange('memo', $changes);

        static::assertStringContainsString($originalText, $oldText);
        static::assertStringContainsString('Updated text via array', $newText);
        static::assertStringContainsString($originalMemo, $oldMemo);
        static::assertStringContainsString('Updated memo via array', $newMemo);
    }

    public function testCalculateChangesWithNoChanges(): void
    {
        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement');

        $updateData = [
            'ident' => $testStatement->getId(),
            'text'  => $testStatement->getText(),
            'memo'  => $testStatement->getMemo(),
        ];

        $changes = $this->sut->calculateChanges($updateData, Statement::class);

        static::assertIsArray($changes);
        static::assertEmpty($changes);
    }

    /**
     * @throws JsonException
     */
    private function getPreUpdateValueOfContentChange(string $key, array $changes): string
    {
        return strip_tags(Json::decodeToArray($changes[$key])[0][0]['old']['lines'][0]);
    }

    /**
     * @throws JsonException
     */
    private function getPostUpdateValueOfContentChange(string $key, array $changes): string
    {
        return strip_tags(Json::decodeToArray($changes[$key])[0][0]['new']['lines'][0]);
    }
}
