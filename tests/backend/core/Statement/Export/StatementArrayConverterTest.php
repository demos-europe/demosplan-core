<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\FileFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\StatementAttachmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementArrayConverter;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class StatementArrayConverterTest extends FunctionalTestCase
{
    /**
     * @var StatementArrayConverter
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(StatementArrayConverter::class);
    }

    /**
     * Test convertIntoExportableArray with Statement object.
     */
    public function testConvertIntoExportableArrayWithStatement(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'a');
        $statement->setMemo('Test memo');
        $statement->_save();

        $result = $this->sut->convertIntoExportableArray($statement->_real());

        // Verify basic statement data is present
        self::assertIsArray($result);
        self::assertArrayHasKey('meta', $result);
        self::assertArrayHasKey('submitDateString', $result);
        self::assertArrayHasKey('countyNames', $result);
        self::assertArrayHasKey('phase', $result);
        self::assertArrayHasKey('tagNames', $result);
        self::assertArrayHasKey('topicNames', $result);
        self::assertArrayHasKey('isClusterStatement', $result);

        // Verify meta data structure
        self::assertIsArray($result['meta']);
        self::assertArrayHasKey('authoredDate', $result['meta']);

        // Verify statement-specific data
        self::assertEquals('Test memo', $result['memo']);
        self::assertEquals('statement_intern_id_internal123', $result['internId']);
        self::assertEquals('statement_author_name_a', $result['meta']['authorName']);

        // Verify arrays are converted properly
        self::assertIsArray($result['tagNames']);
        self::assertIsArray($result['topicNames']);
        self::assertIsArray($result['tags']);
    }

    /**
     * Test convertIntoExportableArray with Segment object.
     */
    public function testConvertIntoExportableArrayWithSegment(): void
    {
        $this->loginTestUser();

        // Create parent statement first
        $parentStatement = $this->createMinimalTestStatement('parent', 'parent123', 'a');
        $parentStatement->setMemo('Parent memo');
        $parentStatement->getMeta()->setOrgaCity('Test City');
        $parentStatement->getMeta()->setOrgaStreet('Test Street');
        $parentStatement->getMeta()->setOrgaPostalCode('12345');
        $parentStatement->getMeta()->setOrgaEmail('test@example.com');
        $parentStatement->getMeta()->setHouseNumber('42');
        $parentStatement->_save();

        $segment = $this->createMinimalTestSegment($parentStatement, 'Isabel Allende');

        $result = $this->sut->convertIntoExportableArray($segment);

        // Verify basic segment data is present
        self::assertIsArray($result);
        self::assertArrayHasKey('meta', $result);
        self::assertArrayHasKey('submitDateString', $result);
        self::assertArrayHasKey('countyNames', $result);
        self::assertArrayHasKey('phase', $result);

        // Verify segment inherits data from parent statement
        self::assertEquals('Parent memo', $result['memo']);
        self::assertEquals('statement_intern_id_parent123', $result['internId']);
        self::assertEquals('statement_author_name_a', $result['meta']['authorName']);
        self::assertEquals('Test City', $result['meta']['orgaCity']);
        self::assertEquals('Test Street', $result['meta']['orgaStreet']);
        self::assertEquals('12345', $result['meta']['orgaPostalCode']);
        self::assertEquals('test@example.com', $result['meta']['orgaEmail']);
        self::assertEquals('42', $result['meta']['houseNumber']);

        // Verify segment uses place instead of status
        self::assertArrayHasKey('status', $result);
        // The status should come from the segment's place, not the parent statement

        // Verify tag and topic data
        self::assertArrayHasKey('tagNames', $result);
        self::assertArrayHasKey('topicNames', $result);
        self::assertArrayHasKey('tags', $result);
        self::assertIsArray($result['tags']);
    }

    /**
     * Test that segments inherit submit date from parent statement.
     */
    public function testConvertIntoExportableArraySegmentInheritsSubmitDate(): void
    {
        $this->loginTestUser();

        $parentStatement = $this->createMinimalTestStatement('parent', 'parent123', 'a');
        $parentStatement->_save();

        $segment = $this->createMinimalTestSegment($parentStatement, 'b');

        $result = $this->sut->convertIntoExportableArray($segment);

        // Verify segment inherits submit date from parent
        self::assertArrayHasKey('submitDateString', $result);
        self::assertEquals($parentStatement->getSubmitDateString(), $result['submitDateString']);
    }

    /**
     * Test tag processing for both statements and segments.
     */
    public function testConvertIntoExportableArrayTagProcessing(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'a');
        $statement->_save();

        $result = $this->sut->convertIntoExportableArray($statement->_real());

        // Verify tag-related fields are properly processed
        self::assertArrayHasKey('tagNames', $result);
        self::assertArrayHasKey('topicNames', $result);
        self::assertArrayHasKey('tags', $result);

        // Tags should be converted from ArrayCollection to array
        self::assertIsArray($result['tags']);

        // Each tag should have topic information
        foreach ($result['tags'] as $tag) {
            self::assertIsArray($tag);
            if (isset($tag['topic'])) {
                self::assertArrayHasKey('topicTitle', $tag);
            }
        }
    }

    /**
     * Test that the method properly handles both StatementInterface types.
     */
    public function testConvertIntoExportableArrayPolymorphicBehavior(): void
    {
        $this->loginTestUser();

        // Test with Statement
        $parentStatement = $this->createMinimalTestStatement('stmt', 'stmt123', 'Parent Statement Author');
        $parentStatement->_save();

        $statementResult = $this->sut->convertIntoExportableArray($parentStatement->_real());

        // Test with Segment
        $segment = $this->createMinimalTestSegment($parentStatement, 'Segment Statement Author');

        $segmentResult = $this->sut->convertIntoExportableArray($segment);

        // Both should have the same basic structure
        $commonFields = ['meta', 'submitDateString', 'countyNames', 'phase', 'tagNames', 'topicNames', 'isClusterStatement'];

        foreach ($commonFields as $field) {
            self::assertArrayHasKey($field, $statementResult, "Statement missing field: $field");
            self::assertArrayHasKey($field, $segmentResult, "Segment missing field: $field");
        }

        // Statement should have its own data
        self::assertEquals('statement_author_name_Parent Statement Author', $statementResult['meta']['authorName']);

        // Segment should inherit from parent
        self::assertEquals('statement_author_name_Parent Statement Author', $segmentResult['meta']['authorName']);
    }

    /**
     * Test isClusterStatement field is correctly set.
     */
    public function testConvertIntoExportableArrayClusterStatement(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'a');
        $statement->_save();

        $result = $this->sut->convertIntoExportableArray($statement->_real());

        self::assertArrayHasKey('isClusterStatement', $result);
        self::assertIsBool($result['isClusterStatement']);
    }

    /**
     * Test that meta data is properly converted to array format.
     */
    public function testConvertIntoExportableArrayMetaConversion(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'a');
        $statement->_save();

        $result = $this->sut->convertIntoExportableArray($statement->_real());

        // Verify meta is converted to array (not object)
        self::assertIsArray($result['meta']);
        self::assertArrayHasKey('authoredDate', $result['meta']);
        self::assertArrayHasKey('authorName', $result['meta']);
    }

    /**
     * Test that computed fields are properly added.
     */
    public function testConvertIntoExportableArrayComputedFields(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'a');
        $statement->_save();

        $result = $this->sut->convertIntoExportableArray($statement->_real());

        // Verify computed fields are present
        self::assertArrayHasKey('submitDateString', $result);
        self::assertArrayHasKey('countyNames', $result);
        self::assertArrayHasKey('phase', $result);

        // These should be arrays/strings, not objects
        self::assertTrue(is_array($result['countyNames']) || is_string($result['countyNames']));
        self::assertIsString($result['phase']);
    }

    private function createMinimalTestSegment(Statement|Proxy $parentStatement, string $submitterNameSuffix): Segment|Proxy
    {
        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $parentStatement->_real(),
            'orderInProcedure'         => 1,
        ]);

        $segment->setPlace(PlaceFactory::createOne([])->_real());
        $segment->_save();

        $segment->getMeta()->setAuthorName("segment_author_name_$submitterNameSuffix");
        $segment->_save();

        return $segment->_real();
    }

    /**
     * Test that fileNames field is correctly set when segmentOrStatement is Statement instance.
     */
    public function testConvertIntoExportableArrayStatementFileNames(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'a');

        // Create file strings - no need to create actual File entities since files property is transient
        $fileStrings = [
            'test-document.pdf:test-file-id-1',
            'test-image.jpg:test-file-id-2',
        ];

        // Work with the real entity and set files
        $realStatement = $statement->_real();
        $realStatement->setFiles($fileStrings);

        // Test the converter with the entity that has files set
        $result = $this->sut->convertIntoExportableArray($realStatement);

        // Verify fileNames field is present for Statement instances
        self::assertArrayHasKey('fileNames', $result);

        // The fileNames should come from the statement's getFileNames() method
        self::assertEquals($realStatement->getFileNames(), $result['fileNames']);

        self::assertEquals(['test-document.pdf', 'test-image.jpg'], $result['fileNames']);

        // Verify we have the expected number of files
        self::assertCount(2, $result['fileNames']);
    }

    /**
     * Test that fileNames field is correctly set when segmentOrStatement is Statement instance.
     * Also tests the getFileNamesWithOriginal method to ensure original files are included.
     */
    public function testConvertIntoExportableArrayStatementFileNamesWithOrginal(): void
    {
        $this->loginTestUser();

        // Now test with original file - create a new statement for this test
        $statementWithOriginal = $this->createMinimalTestStatement('test2', 'internal456', 'b');
        $realStatementWithOriginal = $statementWithOriginal->_real();

        // Create and attach an original file
        $this->addOriginalFileToStatement($statementWithOriginal, 'original-statement.pdf');

        // Test the converter with the entity that has an original file
        $resultWithOriginal = $this->sut->convertIntoExportableArray($realStatementWithOriginal);

        // Verify fileNames field includes the original file
        self::assertArrayHasKey('fileNames', $resultWithOriginal);

        // The fileNames should include both regular files and the original file
        $expectedFileNames = ['original-statement.pdf'];
        self::assertEquals($expectedFileNames, $resultWithOriginal['fileNames']);

        // Verify we have the expected number of files (2 regular + 1 original)
        self::assertCount(1, $resultWithOriginal['fileNames']);

        // Verify that the original file name is included
        self::assertContains('original-statement.pdf', $resultWithOriginal['fileNames']);
    }

    private function addOriginalFileToStatement(Statement|Proxy $statement, string $filename): void
    {
        // Create a File entity
        $file = FileFactory::createOne([
            'name'     => $filename,
            'filename' => $filename,
        ]);

        // Create a StatementAttachment for the original file
        $statementAttachment = StatementAttachmentFactory::createOne([
            'file'      => $file->_real(),
            'type'      => StatementAttachmentInterface::SOURCE_STATEMENT,
            'statement' => $statement->_real(),
        ]);

        // Add to statement's attachments collection
        $statement->addAttachment($statementAttachment->_real());
        $statement->_save();
    }
}
