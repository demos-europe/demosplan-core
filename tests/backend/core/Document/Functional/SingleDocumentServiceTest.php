<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document\SingleDocumentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Logic\Document\SingleDocumentService;
use PhpOffice\PhpSpreadsheet\Calculation\Financial\CashFlow\Single;
use Tests\Base\FunctionalTestCase;

class SingleDocumentServiceTest extends FunctionalTestCase
{
    /**
     * @var SingleDocumentService
     */
    protected $sut;

    /**
     * @var SingleDocument
     */
    protected $testDocument;

    /**
     * @var Procedure
     */
    protected $testProcedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(SingleDocumentService::class);
        $this->testDocument = $this->fixtures->getReference('testSingleDocument1');
        $this->testProcedure = $this->fixtures->getReference('testProcedure');
    }

    private function checkSingleDocumentArray($singleDocumentArray)
    {
        static::assertIsArray($singleDocumentArray);
        static::assertArrayHasKey('ident', $singleDocumentArray);
        $this->checkId($singleDocumentArray['ident']);
        static::assertArrayHasKey('pId', $singleDocumentArray);
        $this->checkId($singleDocumentArray['pId']);
        static::assertArrayHasKey('category', $singleDocumentArray);
        static::assertIsString($singleDocumentArray['category']);
        static::assertArrayHasKey('order', $singleDocumentArray);
        static::assertIsInt($singleDocumentArray['order']);
        static::assertArrayHasKey('title', $singleDocumentArray);
        static::assertIsString($singleDocumentArray['title']);
        static::assertArrayHasKey('symbol', $singleDocumentArray);
        static::assertIsString($singleDocumentArray['symbol']);
        static::assertArrayHasKey('text', $singleDocumentArray);
        static::assertIsString($singleDocumentArray['text']);
        static::assertArrayHasKey('document', $singleDocumentArray);
        static::assertIsString($singleDocumentArray['document']);
        static::assertArrayHasKey('statement_enabled', $singleDocumentArray);
        static::assertIsBool($singleDocumentArray['statement_enabled']);
        static::assertArrayHasKey('deleted', $singleDocumentArray);
        static::assertIsBool($singleDocumentArray['deleted']);
        static::assertArrayHasKey('visible', $singleDocumentArray);
        static::assertIsBool($singleDocumentArray['visible']);
        static::assertArrayHasKey('createdate', $singleDocumentArray);
        static::assertIsNumeric($singleDocumentArray['createdate']);
        static::assertArrayHasKey('elementId', $singleDocumentArray);
        $this->checkId($singleDocumentArray['elementId']);
    }

    private function checkFiltersAndSorting($result, $numberOfFilters = 3)
    {
        static::assertArrayHasKey('filterSet', $result);
        static::assertIsArray($result['filterSet']);
        static::assertEquals($numberOfFilters, sizeof($result['filterSet']));
        static::assertArrayHasKey('total', $result['filterSet']);
        static::assertArrayHasKey('offset', $result['filterSet']);
        static::assertArrayHasKey('limit', $result['filterSet']);

        static::assertArrayHasKey('sortingSet', $result);
    }

    public function testFiltersAndSorting()
    {
        self::markSkippedForCIIntervention();
        // Test for filterArray and sortingArray in result are missing

        $result = $this->sut->getSingleDocumentList($this->testProcedure->getId());
        $this->checkFiltersAndSorting($result);

        $category = 'informationen2';
        $result = $this->sut->getSingleDocumentAdminList($this->testProcedure->getId(), $category);
        $this->checkFiltersAndSorting($result, 4);
        static::assertArrayHasKey('filters', $result['filterSet']);
        static::assertIsArray($result['filterSet']['filters']);

        $result = $this->sut->getSingleDocumentAdminListAll($this->testProcedure->getId());
        $this->checkFiltersAndSorting($result, 4);
        static::assertArrayHasKey('filters', $result['filterSet']);
        static::assertIsArray($result['filterSet']['filters']);
    }

    public function testGetList()
    {
        $result = $this->sut->getSingleDocumentList($this->testProcedure->getId());

        static::assertIsArray($result);
        static::assertEquals(5, sizeof($result));

        static::assertArrayHasKey('total', $result);
        static::assertIsInt($result['total']);
        static::assertEquals(4, $result['total']);

        static::assertArrayHasKey('search', $result);
        static::assertIsString($result['search']);
        static::assertEquals('', $result['search']);

        static::assertArrayHasKey('result', $result);
        static::assertIsArray($result['result']);
        static::assertEquals(4, sizeof($result['result']));
        $this->checkSingleDocumentArray($result['result'][0]);
        static::assertArrayHasKey('modifydate', $result['result'][0]);
        static::assertIsNumeric($result['result'][0]['modifydate']);

        $singleDocumentResult = $result['result'][1];
        $referenceDocument = $this->testDocument;
        static::assertEquals($referenceDocument->getId(), $singleDocumentResult['ident']);
        static::assertEquals($referenceDocument->getPId(), $singleDocumentResult['pId']);
        static::assertEquals($referenceDocument->getCategory(), $singleDocumentResult['category']);
        static::assertEquals($referenceDocument->getOrder(), $singleDocumentResult['order']);
        static::assertEquals($referenceDocument->getTitle(), $singleDocumentResult['title']);
        static::assertEquals($referenceDocument->getSymbol(), $singleDocumentResult['symbol']);
        static::assertEquals($referenceDocument->getText(), $singleDocumentResult['text']);
        static::assertEquals($referenceDocument->getDocument(), $singleDocumentResult['document']);
        static::assertEquals($referenceDocument->getElementId(), $singleDocumentResult['elementId']);
    }

    public function testSort()
    {
        $testDocument1 = $this->testDocument;
        $testDocument2 = $this->fixtures->getReference('testSingleDocument2');
        $testDocument3 = $this->fixtures->getReference('testSingleDocument3');

        $returnValue = $this->sut->sortDocuments([
            $testDocument3->getId(),
            $testDocument1->getId(),
            $testDocument2->getId(),
        ]);
        static::assertTrue($returnValue);
        $result = $this->sut->getSingleDocumentList($this->testProcedure->getId());

        static::assertEquals(2, $result['result'][1]['order']);
        static::assertEquals(3, $result['result'][2]['order']);
        static::assertEquals(1, $result['result'][3]['order']);
    }

    public function testGetAdminList()
    {
        $category = 'informationen2';
        $result = $this->sut->getSingleDocumentAdminList($this->testProcedure->getId(), $category);

        static::assertIsArray($result);
        static::assertEquals(5, sizeof($result));

        static::assertArrayHasKey('total', $result);
        static::assertIsInt($result['total']);
        static::assertEquals(3, $result['total']);

        static::assertArrayHasKey('search', $result);
        static::assertIsString($result['search']);
        static::assertEquals('', $result['search']);

        static::assertArrayHasKey('result', $result);
        static::assertIsArray($result['result']);
        static::assertEquals(3, sizeof($result['result']));
        $this->checkSingleDocumentArray($result['result'][0]);
        static::assertArrayHasKey('modifydate', $result['result'][0]);
        static::assertIsNumeric($result['result'][0]['modifydate']);

        $singleDocumentResult = $result['result'][1];
        $referenceDocument = $this->testDocument;
        static::assertEquals($referenceDocument->getId(), $singleDocumentResult['ident']);
        static::assertEquals($referenceDocument->getPId(), $singleDocumentResult['pId']);
        static::assertEquals($referenceDocument->getCategory(), $singleDocumentResult['category']);
        static::assertEquals($referenceDocument->getOrder(), $singleDocumentResult['order']);
        static::assertEquals($referenceDocument->getTitle(), $singleDocumentResult['title']);
        static::assertEquals($referenceDocument->getSymbol(), $singleDocumentResult['symbol']);
        static::assertEquals($referenceDocument->getText(), $singleDocumentResult['text']);
        static::assertEquals($referenceDocument->getDocument(), $singleDocumentResult['document']);
        static::assertEquals($referenceDocument->getElementId(), $singleDocumentResult['elementId']);
        // static::assertTrue($this->isCurrentTimestamp($singleDocumentResult['createdate']));
        // static::assertTrue($this->isCurrentTimestamp($singleDocumentResult['modifydate']));

        // with category = null we assert no result:
        $result = $this->sut->getSingleDocumentAdminList($this->testProcedure->getId(), null);
        static::assertIsArray($result);
        static::assertEquals(5, sizeof($result));

        static::assertArrayHasKey('total', $result);
        static::assertIsInt($result['total']);
        static::assertEquals(0, $result['total']);
    }

    public function testGetAdminListAll()
    {
        $result = $this->sut->getSingleDocumentAdminListAll($this->testProcedure->getId());

        static::assertIsArray($result);
        static::assertEquals(5, sizeof($result));

        static::assertArrayHasKey('total', $result);
        static::assertIsInt($result['total']);
        static::assertEquals(4, $result['total']);

        static::assertArrayHasKey('search', $result);
        static::assertIsString($result['search']);
        static::assertEquals('', $result['search']);

        static::assertArrayHasKey('result', $result);
        static::assertIsArray($result['result']);
        static::assertEquals(4, sizeof($result['result']));
        $this->checkSingleDocumentArray($result['result'][0]);

        $singleDocumentResult = $result['result'][1];
        $referenceDocument = $this->testDocument;
        static::assertEquals($referenceDocument->getId(), $singleDocumentResult['ident']);
        static::assertEquals($referenceDocument->getPId(), $singleDocumentResult['pId']);
        static::assertEquals($referenceDocument->getCategory(), $singleDocumentResult['category']);
        static::assertEquals($referenceDocument->getOrder(), $singleDocumentResult['order']);
        static::assertEquals($referenceDocument->getTitle(), $singleDocumentResult['title']);
        static::assertEquals($referenceDocument->getSymbol(), $singleDocumentResult['symbol']);
        static::assertEquals($referenceDocument->getText(), $singleDocumentResult['text']);
        static::assertEquals($referenceDocument->getDocument(), $singleDocumentResult['document']);
        static::assertEquals($referenceDocument->getElementId(), $singleDocumentResult['elementId']);
    }

    public function testGet()
    {
        $result = $this->sut->getSingleDocument($this->testDocument->getId());

        $this->checkSingleDocumentArray($result);

        $referenceDocument = $this->testDocument;
        static::assertEquals($referenceDocument->getId(), $result['ident']);
        static::assertEquals($referenceDocument->getPId(), $result['pId']);
        static::assertEquals($referenceDocument->getCategory(), $result['category']);
        static::assertEquals($referenceDocument->getOrder(), $result['order']);
        static::assertEquals($referenceDocument->getTitle(), $result['title']);
        static::assertEquals($referenceDocument->getSymbol(), $result['symbol']);
        static::assertEquals($referenceDocument->getText(), $result['text']);
        static::assertEquals($referenceDocument->getDocument(), $result['document']);
        static::assertEquals($referenceDocument->getElementId(), $result['elementId']);
        static::assertArrayHasKey('modifydate', $result);
        static::assertIsNumeric($result['modifydate']);
    }

    public function testAdd(): void
    {
        $testElement = $this->fixtures->getReference('testSingleDocumentElement');
        $data = [
            'pId'               => $this->testProcedure->getId(),
            'elementId'         => $testElement->getId(),
            'category'          => 'category',
            'order'             => 0,
            'title'             => 'category',
            'text'              => 'category',
            'symbol'            => 'category',
            'document'          => 'category',
            'statement_enabled' => false,
            'visible'           => true,
            'deleted'           => false,
        ];

        $result = $this->sut->addSingleDocument($data);
        $this->checkSingleDocumentArray($result);

        static::assertEquals($data['pId'], $result['pId']);
        static::assertEquals($data['category'], $result['category']);
        static::assertEquals($data['order'], $result['order']);
        static::assertEquals($data['title'], $result['title']);
        static::assertEquals($data['symbol'], $result['symbol']);
        static::assertEquals($data['text'], $result['text']);
        static::assertEquals($data['document'], $result['document']);
        static::assertEquals($data['elementId'], $result['elementId']);
        static::assertTrue($this->isCurrentTimestamp($result['createdate']));
        // before refactoring there was no modifydate key in the result array -> no check for these
    }

    public function testDeleteSingleDocumentWithoutVersions()
    {
        $returnValue = $this->sut->deleteSingleDocument($this->fixtures->getReference('testSingleDocument2')->getId());
        static::assertTrue($returnValue);

        // Die Einträge zu den SingleDocumnents werden gelöscht
        $searchResult = $this->sut->getSingleDocument($this->fixtures->getReference('testSingleDocument2')->getId());
        static::assertNull($searchResult);
    }

    public function testDeleteSingleDocumentWithVersions()
    {
        $returnValue = $this->sut->deleteSingleDocument($this->fixtures->getReference('testSingleDocument1')->getId());
        static::assertTrue($returnValue);

        // Die Einträge zu den SingleDocumnents werden gelöscht
        $searchResult = $this->sut->getSingleDocument($this->fixtures->getReference('testSingleDocument1')->getId());
        static::assertTrue($searchResult['deleted']);
        static::assertFalse($searchResult['visible']);
    }

    public function testDoNotDeleteVersions()
    {
        $singleDoc = $this->sut->getSingleDocument($this->testDocument->getId());
        $versions = $singleDoc['versions'];
        static::assertCount(1, $versions);

        $returnValue = $this->sut->deleteSingleDocument($this->testDocument->getId());
        static::assertTrue($returnValue);

        $versionsAfter = $this->sut->getVersions($this->testDocument->getId());
        static::assertCount(1, $versionsAfter);
    }

    public function testReportOnAddSingleDocumentViaService(): void
    {
        $testElement = $this->fixtures->getReference('testSingleDocumentElement');
        $data = [
            'pId'               => $this->testProcedure->getId(),
            'elementId'         => $testElement->getId(),
            'category'          => 'category',
            'order'             => 0,
            'title'             => 'my title',
            'text'              => 'my text',
            'symbol'            => 'random symbol',
            'document'          => 'some file',
            'statement_enabled' => false,
            'visible'           => true,
            'deleted'           => false,
        ];

        $result = $this->sut->addSingleDocument($data);
        $document = $this->find(SingleDocument::class,$result['id']);
        static::assertInstanceOf(SingleDocument::class, $document);

        $relatedReports = $this->getEntries(ReportEntry::class,
            [
                'group'     => 'singleDocument',
                'category'  => ReportEntry::CATEGORY_ADD,
                'identifierType'  => 'procedure',
                'identifier'  => $this->testProcedure->getId(),
            ]
        );

        static::assertCount(1, $relatedReports);
        $relatedReport = $relatedReports[0];
        static::assertInstanceOf(ReportEntry::class, $relatedReport);
        $messageArray = $relatedReport->getMessageDecoded(false);

        $this->assertSingleDocumentReportEntryMessageKeys($messageArray);
        $this->assertSingleDocumentReportEntryMessageValues($document, $messageArray);
    }

    public function testReportOnUpdateSingleDocumentViaService(): void
    {
        $testDocument = SingleDocumentFactory::createOne();
        $updatedDocument = $this->sut->updateSingleDocument([
            'ident'             => $testDocument->getId(),
            'title'             => 'my updated single document',
            'text'              => 'a updated unique and nice text',
            'statement_enabled' => true,
            'visible'           => true,
        ]);
        $updatedDocument = $this->find(SingleDocument::class, $updatedDocument['ident']);

        $relatedReports = $this->getEntries(ReportEntry::class,
            [
                'group'     => 'singleDocument',
                'category'  => ReportEntry::CATEGORY_UPDATE,
                'identifierType'  => 'procedure',
                'identifier'  => $this->testProcedure->getId(),
            ]
        );

        static::assertCount(1, $relatedReports);
        $relatedReport = $relatedReports[0];
        static::assertInstanceOf(ReportEntry::class, $relatedReport);
        $messageArray = $relatedReport->getMessageDecoded(false);

        $this->assertSingleDocumentReportEntryMessageKeys($messageArray);
        $this->assertSingleDocumentReportEntryMessageValues($updatedDocument, $messageArray);
    }

    public function testReportOnDeleteSingleDocumentViaService():void
    {
        $originDocument = SingleDocumentFactory::createOne();
        $result = $this->sut->deleteSingleDocument($originDocument->getId());
        static::assertTrue($result);
        $relatedReports = $this->getEntries(ReportEntry::class,
            [
                'group'     => 'singleDocument',
                'category'  => ReportEntry::CATEGORY_DELETE,
                'identifierType'  => 'procedure',
                'identifier'  => $this->testProcedure->getId(),
            ]
        );

        static::assertCount(1, $relatedReports);
        $relatedReport = $relatedReports[0];
        static::assertInstanceOf(ReportEntry::class, $relatedReport);
        $messageArray = $relatedReport->getMessageDecoded(false);

        $this->assertSingleDocumentReportEntryMessageKeys($messageArray);
        $this->assertSingleDocumentReportEntryMessageValues($originDocument, $messageArray);
    }

    public function testUdpate()
    {
        $data = [
            'ident'             => $this->testDocument->getId(),
            'title'             => 'category',
            'text'              => 'category',
            'statement_enabled' => true,
            'visible'           => true,
        ];
        $returnValue = $this->sut->updateSingleDocument($data);

        static::assertTrue($this->isCurrentTimestamp($returnValue['modifydate']));
        static::assertEquals($data['text'], $returnValue['text']);
        static::assertEquals($data['statement_enabled'], $returnValue['statement_enabled']);
        static::assertEquals($data['visible'], $returnValue['visible']);
        static::assertEquals($data['title'], $returnValue['title']);
    }

    /**
     * @param array $messageArray
     *
     * @return void
     */
    private function assertSingleDocumentReportEntryMessageKeys(array $messageArray): void
    {
        static::assertArrayHasKey('documentId', $messageArray);
        static::assertArrayHasKey('documentTitle', $messageArray);
        static::assertArrayHasKey('documentText', $messageArray);
        static::assertArrayHasKey('documentCategory', $messageArray);
        static::assertArrayHasKey('relatedFile', $messageArray);
        static::assertArrayHasKey('elementCategory', $messageArray);
        static::assertArrayHasKey('elementTitle', $messageArray);
        static::assertArrayHasKey('visible', $messageArray);
        static::assertArrayHasKey('statement_enabled', $messageArray);
        static::assertArrayHasKey('procedurePhase', $messageArray);
        static::assertArrayHasKey('date', $messageArray);
    }

    private function assertSingleDocumentReportEntryMessageValues(SingleDocument $document, array $messageArray): void
    {
        static::assertEquals($document->getId(), $messageArray['documentId']);
        static::assertEquals($document->getTitle(), $messageArray['documentTitle']);
        static::assertEquals($document->getText(), $messageArray['documentText']);
        static::assertEquals($document->getCategory(), $messageArray['documentCategory']);
        static::assertEquals($document->getFileInfo()->getFileName(), $messageArray['relatedFile']);
        static::assertEquals($document->getElement()->getCategory(), $messageArray['elementCategory']);
        static::assertEquals($document->getElement()->getTitle(), $messageArray['elementTitle']);
        static::assertEquals($document->getVisible(), $messageArray['visible']);
        static::assertEquals($document->isStatementEnabled(), $messageArray['statement_enabled']);
        static::assertEquals($document->getProcedure()->getPhase(), $messageArray['procedurePhase']);
    }
}
