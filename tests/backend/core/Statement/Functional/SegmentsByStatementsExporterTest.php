<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Table;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class SegmentsByStatementsExporterTest extends FunctionalTestCase
{
    private Statement|Proxy|null $testStatement;

    private StatementMeta|Proxy|null $testStatementeMeta;

    /**
     * @var SegmentsByStatementsExporter
     */
    protected $sut;

    private Slugify|Proxy|null $slugify;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentsByStatementsExporter::class);
        $this->slugify = $this->getContainer()->get(Slugify::class);
        $this->testStatement = StatementFactory::createOne();
        $this->testStatementeMeta = StatementMetaFactory::createOne();
        $this->testStatement->setMeta($this->testStatementeMeta->_real());
    }

    public function testMapStatementsToPathInZipWithTrueDuplicate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->sut->mapStatementsToPathInZip(
            [$this->testStatement->_real(), $this->testStatement->_real()],
            false,
            false,
            ''
        );
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testInternalStatementNeedsToBeCensored(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $procedure = ProcedureFactory::createOne();
        $internalStatement = StatementFactory::createOne(['procedure' => $procedure->_real()]);

        $censored = $this->sut->needsToBeCensored(
            $internalStatement->_real(),
            $censorCitizenData,
            $censorInstitutionData
        );

        if ($censorInstitutionData) {
            static::assertTrue($censored);
        } else {
            static::assertFalse($censored);
        }
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testExternalStatementNeedsToBeCensored(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $citizenOrganisation = $this->find(Orga::class, User::ANONYMOUS_USER_ORGA_ID);
        $procedure = ProcedureFactory::createOne();
        $externalStatement = StatementFactory::createOne(['organisation' => $citizenOrganisation, 'procedure' => $procedure]);

        $censored = $this->sut->needsToBeCensored(
            $externalStatement->_real(),
            $censorCitizenData,
            $censorInstitutionData
        );

        if ($censorCitizenData) {
            static::assertTrue($censored);
        } else {
            static::assertFalse($censored);
        }
    }

    /**
     * Test censoring paths on exporting a multiple segments as zip.
     *
     * @dataProvider getCensorParams
     */
    public function testCensorshipOnPathOnExportSegmentsInZip(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $citizenOrganisation = $this->find(Orga::class, User::ANONYMOUS_USER_ORGA_ID);

        $internalStatement = StatementFactory::createOne();
        $externalStatement = StatementFactory::createOne(['organisation' => $citizenOrganisation]);

        static::assertTrue($externalStatement->isSubmittedByCitizen());
        static::assertTrue($internalStatement->isSubmittedByOrganisation());

        $statements = $this->sut->mapStatementsToPathInZip(
            [$externalStatement->_real(), $internalStatement->_real()],
            $censorCitizenData,
            $censorInstitutionData
        );

        foreach ($statements as $key => $statement) {
            if ($statement->isSubmittedByCitizen()) {
                if ($censorCitizenData) {
                    $expectedAKey = $statement->getExternId().'.docx';
                    static::assertEquals($expectedAKey, $key);
                } else {
                    $expectedAKey = $statement->getExternId().'-einreichende-person-unbekannt-eingangsnummer-unbekannt.docx';
                    static::assertEquals($expectedAKey, $key);
                }
            } elseif ($statement->isSubmittedByOrganisation()) {
                if ($censorInstitutionData) {
                    $expectedAKey = $statement->getExternId().'.docx';
                    static::assertEquals($expectedAKey, $key);
                } else {
                    $expectedAKey = $statement->getExternId().'-einreichende-person-unbekannt-eingangsnummer-unbekannt.docx';
                    static::assertEquals($expectedAKey, $key);
                }
            }
        }

        static::assertTrue($externalStatement->isSubmittedByCitizen());
        static::assertTrue($internalStatement->isSubmittedByOrganisation());
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testMapStatementsToPathInZipWithSuperficialDuplicate(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $statementA = $this->createMinimalTestStatement('a', 'a', 'a');
        $statementB = $this->createMinimalTestStatement('b', 'a', 'a');

        $statements = $this->sut->mapStatementsToPathInZip([$statementA->_real(), $statementB->_real()],
            $censorCitizenData,
            $censorInstitutionData
        );

        $shouldStatementABeCensored = ($censorCitizenData && $statementA->isSubmittedByCitizen())
            || ($censorInstitutionData && $statementA->isSubmittedByOrganisation());

        if ($shouldStatementABeCensored) {
            $expectedAKey = 'statement-extern-id-a.docx';
        } else {
            $expectedAKey = 'statement-extern-id-a-statement-author-name-a-statement-intern-id-a.docx';
        }
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA->_real(), $statements[$expectedAKey]);

        $shouldStatementBBeCensored = ($censorCitizenData && $statementB->isSubmittedByCitizen())
            || ($censorInstitutionData && $statementB->isSubmittedByOrganisation());
        if ($shouldStatementBBeCensored) {
            $expectedBKey = 'statement-extern-id-b.docx';
        } else {
            $expectedBKey = 'statement-extern-id-b-statement-author-name-a-statement-intern-id-a.docx';
        }
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB->_real(), $statements[$expectedBKey]);
    }

    /**
     * @dataProvider getCensorParams
     */
    public function testMapStatementsToPathInZipWithoutDuplicate(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $statementA = $this->createMinimalTestStatement('xyz', 'xyz', 'xyz');
        $statementB = $this->createMinimalTestStatement('xyz', 'xyz', 'xyz');

        $statements = $this->sut->mapStatementsToPathInZip([$statementA->_real(), $statementB->_real()],
            $censorCitizenData,
            $censorInstitutionData,
            '');

        $expectedAKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementA->getId(
        ).'.docx';
        self::assertArrayHasKey($expectedAKey, $statements);
        self::assertSame($statementA->_real(), $statements[$expectedAKey]);
        $expectedBKey = 'statement-extern-id-xyz-statement-author-name-xyz-statement-intern-id-xyz-'.$statementB->getId(
        ).'.docx';
        self::assertArrayHasKey($expectedBKey, $statements);
        self::assertSame($statementB->_real(), $statements[$expectedBKey]);
    }

    /**
     * Test censoring on exporting a segment of statement.
     */
    public function testExportCensoredSegments(): void
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        $styles = [
            'orientation'  => 'landscape',
            'marginLeft'   => Converter::cmToTwip(1.27),
            'marginRight'  => Converter::cmToTwip(1.27),
        ];

        $section = $phpWord->addSection($styles);
        $tableHeaders = [];
        $statement = $this->createMinimalTestStatement('xyz', 'xyz', 'xyz');

        $this->sut->exportStatement($section, $statement->_real(), $tableHeaders, false);
        /** @var Table $table */
        $authorName = $section->getElements()[0]->getRows()[0]->getCells()[0]->getElements()[0]->getText();
        static::assertEquals('statement_author_name_xyz', $authorName);

        $section = $phpWord->addSection($styles);
        $this->sut->exportStatement($section, $statement->_real(), $tableHeaders, true);
        /** @var Table $table */
        $authorName = $section->getElements()[0]->getRows()[0]->getCells()[0]->getElements()[0]->getText();
        static::assertEquals('', $authorName);
    }

    /**
     * Test censoring on exporting a single statement submitted by an institution.
     *
     * @dataProvider getCensorParams
     */
    public function testExportCensoringOnInternalStatement(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $procedure = ProcedureFactory::createOne();
        $internalStatement = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $authorName = $this->exportAndGetAuthorName($internalStatement->_real(), $censorCitizenData, $censorInstitutionData);

        if ($censorInstitutionData) {
            static::assertEquals('', $authorName);
        } else {
            static::assertEquals('Einreichende Person unbekannt', $authorName);
        }
    }

    /**
     * Test censoring on exporting a single statement submitted by an citizen.
     *
     * @dataProvider getCensorParams
     */
    public function testExportCensoringOnExternalStatement(
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): void {
        $citizenOrganisation = $this->find(Orga::class, User::ANONYMOUS_USER_ORGA_ID);
        $procedure = ProcedureFactory::createOne();
        $externalStatement = StatementFactory::createOne(
            ['organisation' => $citizenOrganisation, 'procedure' => $procedure]
        );

        $authorName = $this->exportAndGetAuthorName($externalStatement->_real(), $censorCitizenData, $censorInstitutionData);

        if ($censorCitizenData) {
            static::assertEquals('', $authorName);
        } else {
            static::assertEquals('Einreichende Person unbekannt', $authorName);
        }
    }

    private function exportAndGetAuthorName(
        Statement $statement,
        bool $censorCitizenData,
        bool $censorInstitutionData,
    ): string {
        // Word2007
        $exportResult = $this->sut->export(
            $statement->getProcedure(),
            $statement,
            [],
            $censorCitizenData,
            $censorInstitutionData,
            false
        );

        /** @var PhpWord $word */
        $section = $exportResult->getPhpWord()->getSection(0);

        return $section->getElements()[0]->getRows()[0]->getCells()[0]->getElements()[0]->getText();
    }

    private function createMinimalTestStatement(
        string $idSuffix,
        string $internIdSuffix,
        string $submitterNameSuffix,
    ): Statement|Proxy {
        $statement = StatementFactory::createOne();
        $statement->setExternId("statement_extern_id_$idSuffix");
        $statement->_save();
        $statement->setInternId("statement_intern_id_$internIdSuffix");
        $statement->_save();
        $statement->getMeta()->setOrgaName(UserInterface::ANONYMOUS_USER_NAME);
        $statement->_save();
        $statement->getMeta()->setAuthorName("statement_author_name_$submitterNameSuffix");
        $statement->_save();

        return $statement;
    }

    public function getCensorParams(): array
    {
        return [
            [true, true],
            [false, false],
            [true, false],
            [false, true],
        ];
    }

    /**
     * Test convertIntoExportableArray with Statement object.
     */
    public function testConvertIntoExportableArrayWithStatement(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'John Doe');
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
        self::assertEquals('statement_author_name_John Doe', $result['meta']['authorName']);

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
        $parentStatement = $this->createMinimalTestStatement('parent', 'parent123', 'Jane Smith');
        $parentStatement->setMemo('Parent memo');
        $parentStatement->getMeta()->setOrgaCity('Test City');
        $parentStatement->getMeta()->setOrgaStreet('Test Street');
        $parentStatement->getMeta()->setOrgaPostalCode('12345');
        $parentStatement->getMeta()->setOrgaEmail('test@example.com');
        $parentStatement->getMeta()->setHouseNumber('42');
        $parentStatement->_save();

        // Create segment
        $segment = SegmentFactory::createOne([
            'orderInProcedure' => 1,
            'parentStatementOfSegment' => $parentStatement->_real()
        ])->setPlace(PlaceFactory);



        $result = $this->sut->convertIntoExportableArray($segment->_real());

        // Verify basic segment data is present
        self::assertIsArray($result);
        self::assertArrayHasKey('meta', $result);
        self::assertArrayHasKey('submitDateString', $result);
        self::assertArrayHasKey('countyNames', $result);
        self::assertArrayHasKey('phase', $result);

        // Verify segment inherits data from parent statement
        self::assertEquals('Parent memo', $result['memo']);
        self::assertEquals('statement_intern_id_parent123', $result['internId']);
        self::assertEquals('statement_author_name_Jane Smith', $result['meta']['authorName']);
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
     * Test that segments inherit file data from parent statement.
     */
    public function testConvertIntoExportableArraySegmentInheritsFileData(): void
    {
        $this->loginTestUser();

        $parentStatement = $this->createMinimalTestStatement('parent', 'parent123', 'Jane Smith');
        // Mock file names on parent statement
        $parentStatement->setFileNames(['file1.pdf', 'file2.docx']);
        $parentStatement->_save();

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $parentStatement->_real()
        ]);

        $result = $this->sut->convertIntoExportableArray($segment->_real());

        // Verify segment inherits file data from parent
        self::assertArrayHasKey('fileNames', $result);
        self::assertEquals(['file1.pdf', 'file2.docx'], $result['fileNames']);
    }

    /**
     * Test that segments inherit submit date from parent statement.
     */
    public function testConvertIntoExportableArraySegmentInheritsSubmitDate(): void
    {
        $this->loginTestUser();

        $parentStatement = $this->createMinimalTestStatement('parent', 'parent123', 'Jane Smith');
        $parentStatement->_save();

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $parentStatement->_real()
        ]);

        $result = $this->sut->convertIntoExportableArray($segment->_real());

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

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'John Doe');
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
        $statement = $this->createMinimalTestStatement('stmt', 'stmt123', 'Statement Author');
        $statement->_save();

        $statementResult = $this->sut->convertIntoExportableArray($statement->_real());

        // Test with Segment
        $parentStatement = $this->createMinimalTestStatement('parent', 'parent123', 'Parent Author');
        $parentStatement->_save();

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $parentStatement->_real()
        ]);

        $segmentResult = $this->sut->convertIntoExportableArray($segment->_real());

        // Both should have the same basic structure
        $commonFields = ['meta', 'submitDateString', 'countyNames', 'phase', 'tagNames', 'topicNames', 'isClusterStatement'];

        foreach ($commonFields as $field) {
            self::assertArrayHasKey($field, $statementResult, "Statement missing field: $field");
            self::assertArrayHasKey($field, $segmentResult, "Segment missing field: $field");
        }

        // Statement should have its own data
        self::assertEquals('Statement Author', $statementResult['meta']['authorName']);

        // Segment should inherit from parent
        self::assertEquals('Parent Author', $segmentResult['meta']['authorName']);
    }

    /**
     * Test isClusterStatement field is correctly set.
     */
    public function testConvertIntoExportableArrayClusterStatement(): void
    {
        $this->loginTestUser();

        $statement = $this->createMinimalTestStatement('test', 'internal123', 'John Doe');
        $statement->_save();

        $result = $this->sut->convertIntoExportableArray($statement->_real());

        self::assertArrayHasKey('isClusterStatement', $result);
        self::assertIsBool($result['isClusterStatement']);
    }
}
