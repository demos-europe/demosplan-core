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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\Enum\ExportTemplate;
use League\Csv\Reader;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Style\Table;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
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

    private ?string $authorColumnTitle = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentsByStatementsExporter::class);
        $this->slugify = $this->getContainer()->get(Slugify::class);
        $this->testStatement = StatementFactory::createOne();
        $this->testStatementeMeta = StatementMetaFactory::createOne();
        $this->testStatement->setMeta($this->testStatementeMeta->_real());
        $this->authorColumnTitle = $this->getContainer()->get(TranslatorInterface::class)->trans('author');
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
     * Test censoring on exporting a segment of statement.
     */
    public function testExportCensoredSegments(): void
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        $styles = [
            'orientation'  => ExportTemplate::LANDSCAPE->value,
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

    public function testExportAllCsvForUnsegmentedStatement(): void
    {
        $this->pushSessionRequestOntoStack();
        $statement = $this->createMinimalTestStatement('csv-unsegmented', 'csv-unsegmented', 'csv-unsegmented');

        $csv = $this->sut->exportAllCsv($statement->_real());

        static::assertStringStartsWith("\xEF\xBB\xBF", $csv);
        static::assertStringNotContainsString('Array', $csv);

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords(), false);

        static::assertCount(1, $records);
        static::assertSame('statement_author_name_csv-unsegmented', $records[0][$this->authorColumnTitle]);
    }

    public function testExportAllCsvForSegmentedStatement(): void
    {
        $this->pushSessionRequestOntoStack();
        $statement = $this->createMinimalTestStatement('csv-segmented', 'csv-segmented', 'csv-segmented');
        $this->createMinimalTestSegment($statement, 'csv-segment-a');
        $this->createMinimalTestSegment($statement, 'csv-segment-b');

        $csv = $this->sut->exportAllCsv($statement->_real());

        static::assertStringNotContainsString('Array', $csv);

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords(), false);

        static::assertCount(2, $records);
        // The export inherits the parent statement's author for each of its segments' rows —
        // segment-level author names are not used, hence both rows share the same value here.
        $authorNames = array_column($records, $this->authorColumnTitle);
        static::assertSame(['statement_author_name_csv-segmented', 'statement_author_name_csv-segmented'], $authorNames);
    }

    /**
     * `AssessmentTableXlsExporter` (used by `exportAllCsv()`/`exportAllXlsx()` via `selectFormat()`)
     * requires a session on the request stack the moment any of its methods are called — a
     * kernel-booted test never establishes one on its own.
     */
    private function pushSessionRequestOntoStack(): void
    {
        $request = new Request();
        $request->setSession($this->setUpMockSession());
        $this->getContainer()->get(RequestStack::class)->push($request);
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
}
