<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Functional;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document\ElementsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document\ParagraphFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use Tests\Base\FunctionalTestCase;

class ParagraphServiceTest extends FunctionalTestCase
{
    /**
     * @var ParagraphService
     */
    protected $sut;

    /**
     * @var Paragraph
     */
    protected $testParaDocument;

    /**
     * @var Paragraph
     */
    protected $testProcedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(ParagraphService::class);
        $this->testParaDocument = $this->fixtures->getReference('testParagraph1');
        $this->testProcedure = $this->fixtures->getReference('testProcedure');
    }

    private function checkParaDocumentArray($paraDocumentArray)
    {
        static::assertTrue(is_array($paraDocumentArray));
        static::assertArrayHasKey('ident', $paraDocumentArray);
        $this->checkId($paraDocumentArray['ident']);
        static::assertArrayHasKey('elementId', $paraDocumentArray);
        $this->checkId($paraDocumentArray['elementId']);
        static::assertArrayHasKey('category', $paraDocumentArray);
        static::assertTrue(is_string($paraDocumentArray['category']));
        static::assertArrayHasKey('title', $paraDocumentArray);
        static::assertTrue(is_string($paraDocumentArray['title']));
        static::assertArrayHasKey('text', $paraDocumentArray);
        static::assertTrue(is_string($paraDocumentArray['text']));
        static::assertArrayHasKey('order', $paraDocumentArray);
        static::assertTrue(is_integer($paraDocumentArray['order']));
        static::assertArrayHasKey('deleted', $paraDocumentArray);
        static::assertTrue(is_bool($paraDocumentArray['deleted']));
        static::assertArrayHasKey('visible', $paraDocumentArray);
        static::assertTrue(is_numeric($paraDocumentArray['visible']));
        static::assertArrayHasKey('createdate', $paraDocumentArray);
        static::assertTrue(is_numeric($paraDocumentArray['createdate']));
        static::assertArrayHasKey('pId', $paraDocumentArray);
        $this->checkId($paraDocumentArray['pId']);
        static::assertArrayHasKey('parent', $paraDocumentArray);
        static::assertArrayHasKey('children', $paraDocumentArray);
    }

    private function checkFiltersAndSorting($result, $numberOfFilters = 3)
    {
        static::assertArrayHasKey('filterSet', $result);
        static::assertTrue(is_array($result['filterSet']));
        static::assertEquals($numberOfFilters, sizeof($result['filterSet']));
        static::assertArrayHasKey('total', $result['filterSet']);
        static::assertArrayHasKey('offset', $result['filterSet']);
        static::assertArrayHasKey('limit', $result['filterSet']);

        static::assertArrayHasKey('sortingSet', $result);
    }

    public function testFiltersAndSorting()
    {
        self::markSkippedForCIIntervention();
        // Tests for filterArray and sortingArray in result are missing

        $result = $this->sut->getParaDocumentList($this->fixtures->getReference('testProcedure2')->getId(), $this->testParaDocument->getCategory());
        $this->checkFiltersAndSorting(['result' => $result]);

        $result = $this->sut->getParaDocumentList($this->fixtures->getReference('testProcedure2')->getId(), null);
        $this->checkFiltersAndSorting(['result' => $result]);

        $elementId = $this->fixtures->getReference('testElement1')->getId();
        $result = $this->sut->getParaDocumentAdminList($this->fixtures->getReference('testProcedure2')->getId(), $elementId, null, true);
        $this->checkFiltersAndSorting($result, 4);
        static::assertArrayHasKey('filters', $result['filterSet']);

        $result = $this->sut->getParaDocumentAdminListAll([$this->fixtures->getReference('testProcedure2')->getId()]);
        $this->checkFiltersAndSorting($result, 4);
        static::assertArrayHasKey('filters', $result['filterSet']);
    }

    public function testgetParaDocumentAdminList()
    {
        $elementId = $this->fixtures->getReference('testElement1')->getId();
        $procedureId = $this->fixtures->getReference('testProcedure2')->getId();

        $resultList = $this->sut->getParaDocumentAdminList($procedureId, $elementId, null);

        $result = $resultList['result'];

        static::assertNotNull($result);
        static::assertTrue(is_array($result));
        static::assertCount(3, $result);
        static::assertEquals(3, $resultList['total']);
        static::assertTrue(is_string($resultList['search']));
        static::assertTrue($result[0] instanceof Paragraph);
        static::assertTrue($result[1] instanceof Paragraph);
        static::assertTrue($result[2] instanceof Paragraph);
    }

    public function testGetParaDocumentVersion()
    {
        $versionId = $this->fixtures->getReference('testParagraphVersion')->getId();
        $result = $this->sut->getParaDocumentVersion($versionId);

        $versionId2 = $this->fixtures->getReference('testParagraph2Version')->getId();

        static::assertNotNull($result);
        static::assertTrue(is_array($result));
    }

    public function testGetList()
    {
        $elementId = $this->fixtures->getReference('testElement1')->getId();
        $result = $this->sut->getParaDocumentList($this->fixtures->getReference('testProcedure2')->getId(), $elementId);

        static::assertCount(3, $result);
        $this->checkParaDocumentArray($result[1]);

        $paraDocumentResult = $result[0];
        $referenceParaDocument = $this->testParaDocument;
        static::assertEquals($referenceParaDocument->getId(), $paraDocumentResult['ident']);
        static::assertEquals($referenceParaDocument->getElementId(), $paraDocumentResult['elementId']);
        static::assertEquals($referenceParaDocument->getCategory(), $paraDocumentResult['category']);
        static::assertEquals($referenceParaDocument->getTitle(), $paraDocumentResult['title']);
        static::assertEquals($referenceParaDocument->getText(), $paraDocumentResult['text']);
        static::assertEquals($referenceParaDocument->getOrder(), $paraDocumentResult['order']);
        static::assertFalse($paraDocumentResult['deleted']);
        static::assertEquals(1, $paraDocumentResult['visible']);
        static::assertTrue($this->isCurrentTimestamp($paraDocumentResult['createdate']));
        static::assertTrue($this->isCurrentTimestamp($paraDocumentResult['modifydate']));
        static::assertEquals($referenceParaDocument->getPId(), $paraDocumentResult['pId']);

        // if category = null -> 0 results because of there are no entries with category = null
        $result = $this->sut->getParaDocumentList($this->fixtures->getReference('testProcedure2')->getId(), null);
        static::assertCount(0, $result);
    }

    public function testGetAdminList()
    {
        $elementId = $this->fixtures->getReference('testElement1')->getId();
        $result = $this->sut->getParaDocumentAdminList($this->fixtures->getReference('testProcedure2')->getId(), $elementId, null, true);

        static::assertTrue(is_array($result));
        static::assertEquals(5, sizeof($result));

        static::assertArrayHasKey('total', $result);
        static::assertTrue(is_integer($result['total']));
        static::assertEquals(3, $result['total']);

        static::assertArrayHasKey('search', $result);
        static::assertTrue(is_string($result['search']));
        static::assertEquals('', $result['search']);

        static::assertArrayHasKey('result', $result);
        static::assertTrue(is_array($result['result']));
        static::assertEquals(3, sizeof($result['result']));
        $this->checkParaDocumentArray($result['result'][0]);
        static::assertArrayHasKey('modifydate', $result['result'][0]);
        static::assertTrue(is_numeric($result['result'][1]['modifydate']));

        $paraDocumentResult = $result['result'][0];
        $referenceParaDocument = $this->testParaDocument;
        static::assertEquals($referenceParaDocument->getId(), $paraDocumentResult['ident']);
        static::assertEquals($referenceParaDocument->getElementId(), $paraDocumentResult['elementId']);
        static::assertEquals($referenceParaDocument->getCategory(), $paraDocumentResult['category']);
        static::assertEquals($referenceParaDocument->getTitle(), $paraDocumentResult['title']);
        static::assertEquals($referenceParaDocument->getText(), $paraDocumentResult['text']);
        static::assertEquals($referenceParaDocument->getOrder(), $paraDocumentResult['order']);
        static::assertFalse($paraDocumentResult['deleted']);
        static::assertEquals(1, $paraDocumentResult['visible']);
        static::assertTrue($this->isCurrentTimestamp($paraDocumentResult['createdate']));
        static::assertTrue($this->isCurrentTimestamp($paraDocumentResult['modifydate']));
        static::assertEquals($referenceParaDocument->getPId(), $paraDocumentResult['pId']);

        // with category = null we assert no result:
        $result = $this->sut->getParaDocumentAdminList($this->fixtures->getReference('testProcedure')->getId(), null, null, true);
        static::assertTrue(is_array($result));
        static::assertEquals(5, sizeof($result));

        static::assertArrayHasKey('total', $result);
        static::assertTrue(is_integer($result['total']));
        static::assertEquals(0, $result['total']);
    }

    public function testGetAdminListAll()
    {
        $result = $this->sut->getParaDocumentAdminListAll([$this->fixtures->getReference('testProcedure2')->getId()]);

        static::assertTrue(is_array($result));
        static::assertEquals(5, sizeof($result));

        static::assertArrayHasKey('total', $result);
        static::assertTrue(is_integer($result['total']));
        static::assertEquals(15, $result['total']);

        static::assertArrayHasKey('search', $result);
        static::assertTrue(is_string($result['search']));
        static::assertEquals('', $result['search']);

        static::assertArrayHasKey('result', $result);
        static::assertTrue(is_array($result['result']));
        static::assertEquals(15, sizeof($result['result']));
        $this->checkParaDocumentArray($result['result'][0]);

        $paraDocumentResult = $result['result'][0];
        $referenceParaDocument = $this->testParaDocument; // TestParagraph1
        static::assertEquals($referenceParaDocument->getId(), $paraDocumentResult['ident']);
        static::assertEquals($referenceParaDocument->getElementId(), $paraDocumentResult['elementId']);
        static::assertEquals($referenceParaDocument->getCategory(), $paraDocumentResult['category']);
        static::assertEquals($referenceParaDocument->getTitle(), $paraDocumentResult['title']);
        static::assertEquals($referenceParaDocument->getText(), $paraDocumentResult['text']);
        static::assertEquals($referenceParaDocument->getOrder(), $paraDocumentResult['order']);
        static::assertFalse($paraDocumentResult['deleted']);
        static::assertEquals(1, $paraDocumentResult['visible']);
        static::assertTrue($this->isCurrentTimestamp($paraDocumentResult['createdate']));
        static::assertTrue($this->isCurrentTimestamp($paraDocumentResult['modifydate']));
        static::assertEquals($referenceParaDocument->getPId(), $paraDocumentResult['pId']);
    }

    public function testGet()
    {
        $result = $this->sut->getParaDocument($this->testParaDocument->getId());

        $this->checkParaDocumentArray($result);

        $referenceDocument = $this->testParaDocument;
        static::assertEquals($referenceDocument->getId(), $result['ident']);
        static::assertEquals($referenceDocument->getElementId(), $result['elementId']);
        static::assertEquals($referenceDocument->getCategory(), $result['category']);
        static::assertEquals($referenceDocument->getTitle(), $result['title']);
        static::assertEquals($referenceDocument->getText(), $result['text']);
        static::assertEquals($referenceDocument->getOrder(), $result['order']);
        static::assertFalse($result['deleted']);
        static::assertEquals(1, $result['visible']);
        static::assertTrue($this->isCurrentTimestamp($result['createdate']));
        static::assertArrayHasKey('modifydate', $result);
        static::assertTrue(is_numeric($result['modifydate']));
        static::assertTrue($this->isCurrentTimestamp($result['modifydate']));
        static::assertEquals($referenceDocument->getPId(), $result['pId']);
    }

    public function testAdd(): void
    {
        $paragraph = [
            'pId'       => $this->fixtures->getReference('testProcedure')->getId(),
            'elementId' => $this->fixtures->getReference('testReasonElement')->getId(),
            'category'  => 'begruendung',
            'title'     => ',my test title',
            'text'      => 'my test text',
            'order'     => 0,
            'deleted'   => false,
            'visible'   => 1,
        ];

        $result = $this->sut->addParaDocument($paragraph);
        $this->checkId($result['ident']);
        $this->checkParaDocumentArray($result);
        static::assertEquals($paragraph['pId'], $result['pId']);
        static::assertEquals($paragraph['elementId'], $result['elementId']);
        static::assertEquals($paragraph['title'], $result['title']);
        static::assertEquals($paragraph['category'], $result['category']);
        static::assertEquals($paragraph['text'], $result['text']);
        static::assertEquals($paragraph['order'], $result['order']);
        static::assertEquals($paragraph['deleted'], $result['deleted']);
        static::assertEquals($paragraph['visible'], $result['visible']);
        static::assertEquals(null, $result['parent']);
        static::assertCount(0, $result['children']);
        static::assertTrue($this->isCurrentTimestamp($result['createdate']));
    }

    public function testUpdate()
    {
        $paragraph = [
            'ident'    => $this->testParaDocument->getId(),
            'category' => 'updatedCategory',
            'visible'  => 0,
        ];

        static::assertEquals(1, $this->testParaDocument->getVisible());

        $updated = $this->sut->updateParaDocument($paragraph);
        $this->checkId($updated['ident']);
        $this->checkParaDocumentArray($updated);
        static::assertEquals($paragraph['category'], $updated['category']);
        static::assertEquals(0, $updated['visible']);
        static::assertTrue($this->isCurrentTimestamp($updated['modifydate']));

        $result = $this->sut->getParaDocument($paragraph['ident']);
        static::assertEquals($result['title'], $updated['title']);
        static::assertEquals($result['elementId'], $updated['elementId']);
        static::assertEquals($result['pId'], $updated['pId']);
        static::assertEquals($result['text'], $updated['text']);
        static::assertEquals($result['order'], $updated['order']);
        static::assertEquals($result['deleted'], $updated['deleted']);
        static::assertEquals($result['visible'], $updated['visible']);
    }

    public function testDeleteParagraphWithVersions()
    {
        self::markSkippedForCIIntervention();

        $paragraphBefore = $this->sut->getParaDocument($this->testParaDocument->getId());
        // create ParadocVersio$n entry:
        $this->sut->getDoctrine()->getManager()
            ->getRepository(ParagraphVersion::class)
            ->createVersion($this->getEntityManager()->getReference(Paragraph::class, $paragraphBefore['ident']));

        $numberOfEntriesBefore = $this->countEntries(Paragraph::class);
        $numberOfVersionsBefore = $this->countEntries(ParagraphVersion::class);

        $deleted = $this->sut->deleteParaDocument(['ident' => $paragraphBefore['ident']]);
        static::assertTrue($deleted);

        $numberOfEntriesAfter = $this->countEntries(Paragraph::class);
        $numberOfVersionsAfter = $this->countEntries(ParagraphVersion::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter);
        static::assertEquals($numberOfVersionsBefore, $numberOfVersionsAfter);

        $paragraphAfter = $this->sut->getParaDocument($this->testParaDocument->getId());
        static::assertTrue($paragraphAfter['deleted']);
        static::assertEquals(0, $paragraphAfter['visible']);
    }

    public function testDeleteParagraphWithoutVersions()
    {
        $paragraphBefore = $this->sut->getParaDocument($this->fixtures->getReference('testParagraph3')->getId());

        $numberOfEntriesBefore = $this->countEntries(Paragraph::class);
        static::assertCount(0, $paragraphBefore['versions']);

        $deleted = $this->sut->deleteParaDocument(['ident' => $paragraphBefore['ident']]);
        static::assertTrue($deleted);

        $numberOfEntriesAfter = $this->countEntries(Paragraph::class);
        static::assertEquals($numberOfEntriesAfter + 1, $numberOfEntriesBefore);

        $paragraphAfter = $this->sut->getParaDocument($this->fixtures->getReference('testParagraph3')->getId());
        static::assertNull($paragraphAfter);
    }

    public function testDeleteMultiple()
    {
        $toDelete = [];
        $toDelete[0] = $this->fixtures->getReference('testParagraph1')->getId();
        $toDelete[1] = $this->fixtures->getReference('testParagraph2')->getId();
        $toDelete[2] = $this->fixtures->getReference('testParagraph3')->getId();

        $numberOfEntriesBefore = $this->countEntries(Paragraph::class);

        $deleted = $this->sut->deleteParaDocument($toDelete);
        self::assertTrue($deleted);

        $numberOfEntriesAfter = $this->countEntries(Paragraph::class);
        // testParagraph2 hat Versionen, deshalb wird dieser nicht gelöscht, sondern nur als gelöscht geflaggt
        static::assertEquals($numberOfEntriesAfter + 2, $numberOfEntriesBefore);

        // versions gelöscht?
        // $this->getVersions == null
    }

    public function testGetMaxOrderFromElement()
    {
        $maxOrder = $this->sut->getMaxOrderFromElement($this->fixtures->getReference('testParagraph1')->getElementId());
        static::assertEquals(2, $maxOrder);
    }

    public function testCalculateLastOrder()
    {
        $maxOrder = $this->sut->calculateLastOrder(
            $this->fixtures->getReference('testParagraph_tree_1_1')->getId()
        );
        // the element 1_1 itself has order 1. the element 1_1 also has 3
        // subordinated paragraphs, so the result should be 4
        static::assertEquals(4, $maxOrder);
    }

    public function testGetSameLevelParagraphs()
    {
        /*
         * Compile a list of paragraphs that are on the same level for the paragraph to move
         * These are either the children of the paragraph's parent or each paragraph
         * that does not have any parent in case the paragraph is on the top level.
         */
        $paragraphWithParent = $this->fixtures->getReference('testParagraph_tree_1_2');

        $elementId = $paragraphWithParent->getElementId();
        $procedureId = $paragraphWithParent->getPid();

        $list = $this->sut->getSameLevelParagraphs($procedureId, $elementId, $paragraphWithParent);
        $titlesWithParent = [
            '1_1',
            '1_2',
            '1_3',
        ];
        static::assertCount(3, $list);
        foreach ($list as $paragraph) {
            static::assertContains($paragraph->getTitle(), $titlesWithParent);
        }

        $paragraphWithoutParent = $this->fixtures->getReference('testParagraph_tree_1');
        $list = $this->sut->getSameLevelParagraphs($procedureId, $elementId, $paragraphWithoutParent);
        $titlesWithoutParent = [
            '1',
            '2',
        ];
        static::assertCount(2, $list);
        foreach ($list as $paragraph) {
            static::assertContains($paragraph->getTitle(), $titlesWithoutParent);
        }
    }

    public function testDetermineNextParagraph()
    {
        $paragraph = $this->fixtures->getReference('testParagraph_tree_1_2');

        $elementId = $paragraph->getElementId();
        $procedureId = $paragraph->getPid();

        $list = $this->sut->getSameLevelParagraphs($procedureId, $elementId, $paragraph);

        $firstParagraph = $this->sut->determineNextParagraph('up', $list, $paragraph);
        static::assertEquals($firstParagraph->getTitle(), '1_1');

        $lastParagraph = $this->sut->determineNextParagraph('down', $list, $paragraph);
        static::assertEquals($lastParagraph->getTitle(), '1_3');

        // if its the topmost paragraph on the level it is supposed to return itself
        $firstParagraph2 = $this->sut->determineNextParagraph('up', $list, $firstParagraph);
        static::assertEquals($firstParagraph, $firstParagraph2);

        // if its the last paragraph in the level its supposed to return itself
        $lastParagraph2 = $this->sut->determineNextParagraph('down', $list, $lastParagraph);
        static::assertEquals($lastParagraph, $lastParagraph2);
    }

    public function testUpdateParagraphOrdersOnReOrdering()
    {
        self::markSkippedForCIIntervention();
        // Tests for filterArray and sortingArray in result are missing

        // check initial order
        $toCheck = ['1', '1_1', '1_1_1', '1_1_2', '1_1_3', '1_2', '1_2_1', '1_3', '1_3_1', '1_3_2', '2', '2_1'];
        $paragraphs = [];
        $orderCheck = 0;
        foreach ($toCheck as $title) {
            $paragraph = $this->fixtures->getReference('testParagraph_tree_'.$title);
            $paragraphs[$title] = $paragraph->getId();
            static::assertEquals($orderCheck++, $paragraph->getOrder());
        }

        $paragraphToMove = $this->fixtures->getReference('testParagraph_tree_1_2');
        $newParentParagraph = $this->fixtures->getReference('testParagraph_tree_1_1_1');

        $maxOrder = $this->sut->calculateLastOrder($newParentParagraph->getId());

        $paragraphToMove->setOrder($maxOrder + 1);

        $offset = $this->sut->incrementChildrenOrders($paragraphToMove->getId(), $maxOrder + 1);

        $this->sut->incrementSubsequentOrders($maxOrder, $paragraphToMove->getElementId(), $offset + 1);

        $this->sut->updateParaDocumentObject($paragraphToMove);
        $lastOrder = -1;

        // ATTENTION: *not* the same as above ;)
        // somehow this is still not functional. Orders are correct in database
        //       but obtained objects yield wrong values

        $toCheck = ['1', '1_1', '1_1_1', '1_2', '1_2_1', '1_1_2', '1_1_3', '1_3', '1_3_1', '1_3_2', '2', '2_1'];
        foreach ($toCheck as $title) {
            $paragraph = $this->sut->getParaDocument($paragraphs[$title]);
            echo 'after:  '.$title.' '.$paragraph['order'].' '.$lastOrder."\n";
            static::assertGreaterThan($lastOrder, $lastOrder = $paragraph['order']);
        }
    }

    /*
     * Ensures that the orders of paragraph versions get updated
     * when their respective paragraphs are assigned new order
     * numbers.
     */
    public function testParagraphToVersionOrderUpdate()
    {
        $paragraphToMove = $this->fixtures->getReference('testParagraph1');
        $paragraph2 = $this->fixtures->getReference('testParagraph2');

        foreach ($paragraphToMove->getVersions() as $version) {
            static::assertEquals($version->getOrder(), $paragraphToMove->getOrder());
        }
        foreach ($paragraph2->getVersions() as $version) {
            static::assertEquals($version->getOrder(), $paragraph2->getOrder());
        }

        $maxOrder = $this->sut->calculateLastOrder($paragraphToMove->getId());
        $paragraphToMove->setOrder($maxOrder + 1);
        $offset = $this->sut->incrementChildrenOrders($paragraphToMove->getId(), $maxOrder + 1);
        $this->sut->incrementSubsequentOrders($maxOrder, $paragraphToMove->getElementId(), $offset + 1);

        foreach ($paragraphToMove->getVersions() as $version) {
            static::assertEquals($version->getOrder(), $paragraphToMove->getOrder());
        }
        foreach ($paragraph2->getVersions() as $version) {
            static::assertEquals($version->getOrder(), $paragraph2->getOrder());
        }
    }

    public function testGetVersionsFromParagraph()
    {
        self::markSkippedForCIIntervention();

        $paragraph = $this->fixtures->getReference('testParagraph2');
        $result = $this->sut->getParaDocumentVersionOfParagraph($paragraph);

        static::assertNotNull($result);
        static::assertTrue(is_array($result));
        static::assertCount(2, $result);
        static::assertEquals($result[0], $this->fixtures->getReference('testParagraph2Version'));
        static::assertEquals($result[1], $this->fixtures->getReference('testParagraph3Version'));
    }

    public function testCreateVersion()
    {
        /** @var Paragraph $paragraph */
        $paragraph = $this->fixtures->getReference('testParagraph1');
        $this->assertInstanceOf(Paragraph::class, $paragraph);

        $currentDate = new DateTime();

        /** @var ParagraphVersion $version */
        $version = $this->sut->createVersion($paragraph);
        $this->assertInstanceOf(ParagraphVersion::class, $version);

        $this->assertEquals($paragraph, $version->getParagraph());
        $this->assertEquals($paragraph->getProcedure(), $version->getProcedure());
        $this->assertEquals($paragraph->getElement(), $version->getElement());
        $this->assertEquals($paragraph->getCategory(), $version->getCategory());
        $this->assertEquals($paragraph->getTitle(), $version->getTitle());
        $this->assertEquals($paragraph->getText(), $version->getText());
        $this->assertEquals($paragraph->getOrder(), $version->getOrder());
        $this->assertEquals($paragraph->getVisible(), $version->getVisible());
        $this->assertEquals($paragraph->getDeleted(), $version->getDeleted());
    }

    public function testReportOnCreateParagraph(): void
    {
        $testElement = ElementsFactory::new()->create(['procedure' => $this->testProcedure]);

        $data = [
            'procedure' => $testElement->getProcedure(),
            'pId'       => $testElement->getProcedure()->getId(),
            'elementId' => $testElement->getId(),
            'element'   => $testElement,
            'category'  => 'begruendung',
            'title'     => 'my test title',
            'text'      => 'my test text',
            'order'     => 0,
            'deleted'   => false,
            'visible'   => 1,
        ];

        $result = $this->sut->addParaDocument($data);
        $paragraph = $this->find(Paragraph::class, $result['id']);
        static::assertInstanceOf(Paragraph::class, $paragraph);

        $relatedReports = $this->getEntries(ReportEntry::class,
            [
                'group'           => 'paragraph',
                'category'        => ReportEntry::CATEGORY_ADD,
                'identifierType'  => 'procedure',
                'identifier'      => $this->testProcedure->getId(),
            ]
        );

        static::assertCount(1, $relatedReports);
        $relatedReport = $relatedReports[0];
        static::assertInstanceOf(ReportEntry::class, $relatedReport);
        $messageArray = $relatedReport->getMessageDecoded(false);

        $this->assertParagraphReportEntryMessageKeys($messageArray);
        $this->assertParagraphReportEntryMessageValues($paragraph, $messageArray);
    }

    public function testReportOnUpdateParagraph(): void
    {
        $testParagraph = ParagraphFactory::createOne();
        $updatedParagraph = $this->sut->updateParaDocument([
            'ident'             => $testParagraph->getId(),
            'title'             => 'my updated paragraph',
            'text'              => 'a updated unique and nice text',
            'statement_enabled' => true,
            'order'             => 1,
            'visible'           => true,
        ]);
        $updatedParagraph = $this->find(Paragraph::class, $updatedParagraph['ident']);

        $relatedReports = $this->getEntries(ReportEntry::class,
            [
                'group'           => 'paragraph',
                'category'        => ReportEntry::CATEGORY_UPDATE,
                'identifierType'  => 'procedure',
                'identifier'      => $testParagraph->getProcedure()->getId(),
            ]
        );

        static::assertCount(1, $relatedReports);
        $relatedReport = $relatedReports[0];
        static::assertInstanceOf(ReportEntry::class, $relatedReport);
        $messageArray = $relatedReport->getMessageDecoded(false);

        $this->assertParagraphReportEntryMessageKeys($messageArray);
        $this->assertParagraphReportEntryMessageValues($updatedParagraph, $messageArray);
    }

    public function testReportOnDeleteParagraph(): void
    {
        $originParagraph = ParagraphFactory::createOne();
        $originId = $originParagraph->getId();
        $procedureId = $originParagraph->getProcedure()->getId();
        $result = $this->sut->deleteParaDocument($originParagraph->getId());
        static::assertTrue($result);
        $relatedReports = $this->getEntries(ReportEntry::class,
            [
                'group'           => 'paragraph',
                'category'        => ReportEntry::CATEGORY_DELETE,
                'identifierType'  => 'procedure',
                'identifier'      => $procedureId,
            ]
        );

        static::assertCount(1, $relatedReports);
        $relatedReport = $relatedReports[0];
        static::assertInstanceOf(ReportEntry::class, $relatedReport);
        $messageArray = $relatedReport->getMessageDecoded(false);

        $this->assertParagraphReportEntryMessageKeys($messageArray);
        $this->assertParagraphReportEntryMessageValues($originParagraph->_real(), $messageArray, $originId);
    }

    private function assertParagraphReportEntryMessageKeys(array $messageArray): void
    {
        static::assertArrayHasKey('id', $messageArray);
        static::assertArrayHasKey('title', $messageArray);
        static::assertArrayHasKey('text', $messageArray);
        static::assertArrayHasKey('category', $messageArray);
        static::assertArrayHasKey('relatedElementCategory', $messageArray);
        static::assertArrayHasKey('relatedElementTitle', $messageArray);
        static::assertArrayHasKey('visible', $messageArray);
        static::assertArrayHasKey('keyOfInternalPhase', $messageArray);
        static::assertArrayHasKey('keyOfEternalPhase', $messageArray);
        static::assertArrayHasKey('nameOfInternalPhase', $messageArray);
        static::assertArrayHasKey('nameOfExternalPhase', $messageArray);
        static::assertArrayHasKey('date', $messageArray);
    }

    private function assertParagraphReportEntryMessageValues(
        Paragraph $paragraph,
        array $messageArray,
        ?string $originId = null,
    ): void {
        $id = $originId ?? $paragraph->getId();

        static::assertEquals($id, $messageArray['id']);
        static::assertEquals($paragraph->getTitle(), $messageArray['title']);
        static::assertEquals($paragraph->getText(), $messageArray['text']);
        static::assertEquals($paragraph->getCategory(), $messageArray['category']);
        static::assertEquals($paragraph->getElement()->getCategory(), $messageArray['relatedElementCategory']);
        static::assertEquals($paragraph->getElement()->getTitle(), $messageArray['relatedElementTitle']);
        static::assertEquals($paragraph->getVisible(), $messageArray['visible']);
        static::assertEquals($paragraph->getProcedure()->getPhase(), $messageArray['keyOfInternalPhase']);
        static::assertEquals($paragraph->getProcedure()->getPublicParticipationPhase(), $messageArray['keyOfEternalPhase']);
        static::assertEquals($paragraph->getProcedure()->getPhaseName(), $messageArray['nameOfInternalPhase']);
        static::assertEquals($paragraph->getProcedure()->getPublicParticipationPhaseName(), $messageArray['nameOfExternalPhase']);
    }
}
