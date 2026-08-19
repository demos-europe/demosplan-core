<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\CsvStatementImporter;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Base\FunctionalTestCase;

class CsvStatementImporterTest extends FunctionalTestCase
{
    protected ?CsvStatementImporter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(CsvStatementImporter::class);
    }

    public function testValidFileCreatesOneStatementPerRow(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('valid.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(6, $result->getStatementCount());
        self::assertSame(0, $result->getSegmentCount());
    }

    public function testSubmitterDataIsMappedFromColumns(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('no_bom.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        [$public, $institution] = $result->getStatements();

        $publicMeta = $public->getMeta();
        self::assertSame(Statement::EXTERNAL, $public->getPublicStatement());
        self::assertSame('Sir Knuff', $publicMeta->getSubmitName());
        self::assertSame('Sir Knuff', $publicMeta->getAuthorName());
        self::assertSame('theo@mail.de', $publicMeta->getOrgaEmail());
        self::assertSame('Teststr 1124', $publicMeta->getOrgaStreet());
        self::assertSame('12345', $publicMeta->getOrgaPostalCode());
        self::assertSame('Berlin', $publicMeta->getOrgaCity());
        self::assertSame(Statement::SUBMIT_TYPE_EMAIL, $public->getSubmitType());
        self::assertSame('2020-03-28', $public->getSubmitObject()->format('Y-m-d'));
        self::assertSame('2020-03-22', $publicMeta->getAuthoredDateObject()->format('Y-m-d'));

        $institutionMeta = $institution->getMeta();
        self::assertSame(Statement::INTERNAL, $institution->getPublicStatement());
        self::assertSame('Amt für Beispiele', $institutionMeta->getOrgaName());
        self::assertSame('MFG', $institutionMeta->getOrgaDepartmentName());
        self::assertSame('1', $institutionMeta->getHouseNumber());
        self::assertSame(Statement::SUBMIT_TYPE_LETTER, $institution->getSubmitType());
        self::assertSame('K85/789789789', $institution->getInternId());
    }

    public function testMultilineTextIsConvertedToLineBreaks(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('valid.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        $texts = array_map(static fn (Statement $statement): string => $statement->getText(), $result->getStatements());
        $textWithLineBreaks = array_filter($texts, static fn (string $text): bool => str_contains($text, '<br>'));

        self::assertNotEmpty($textWithLineBreaks, 'Quoted multiline cells should keep their line breaks as <br>');
        foreach ($texts as $text) {
            self::assertStringNotContainsString("\n", $text);
        }
    }

    public function testEmptyRowsAreSkipped(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('empty_rows.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(2, $result->getStatementCount());
    }

    public function testMissingColumnIsReportedAndNothingIsImported(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('missing_column.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
        self::assertStringContainsString(
            'Stellungnahmetext',
            $this->describeErrors($result->getErrorsAsArray())
        );
    }

    public function testUnreadableHeaderIsReportedInsteadOfThrowing(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('duplicate_column.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
    }

    public function testEmptyFileIsReportedAsMissingColumns(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('empty_file.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
    }

    /**
     * A file that includes Institution/Abteilung must leave them empty, not omit them, to mark every
     * row as public - an empty value, not an absent column.
     */
    public function testInstitutionColumnAlwaysEmptyImportsEveryRowAsPublic(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('institution_column_empty.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(2, $result->getStatementCount());
        foreach ($result->getStatements() as $statement) {
            self::assertSame(Statement::EXTERNAL, $statement->getPublicStatement());
        }
    }

    /**
     * A file holding only public statements has no use for Institution/Abteilung, so omitting the pair
     * entirely is accepted the same way as including it but leaving it empty on every row.
     */
    public function testAbsentInstitutionColumnsImportEveryRowAsPublic(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('institution_column_absent.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(1, $result->getStatementCount());
        self::assertSame(Statement::EXTERNAL, $result->getStatements()[0]->getPublicStatement());
    }

    /**
     * Only one of the two institution columns present is an inconsistent file, not a further valid
     * variant, and must be rejected the same way any other missing column is.
     */
    public function testOnlyOneInstitutionColumnPresentIsReportedAsMissingColumn(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('institution_column_partial.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
        self::assertStringContainsString('Abteilung', $this->describeErrors($result->getErrorsAsArray()));
    }

    public function testIso8859EncodedHeaderIsAccepted(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('iso8859_header.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(1, $result->getStatementCount());
    }

    /**
     * Mirrors StatementSpreadsheetImporter::process(): the first row for a given Eingangsnummer wins,
     * later rows sharing it are silently skipped instead of failing the whole file. "Silently" only
     * means the import is not aborted - the user still gets a warning naming the skipped row, so they
     * know the file was not imported in full.
     */
    public function testDuplicateInternIdWithinFileImportsFirstRowAndWarnsAboutLaterOnes(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('duplicate_internid_in_file.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(1, $result->getStatementCount());
        self::assertSame('DUP-001', $result->getStatements()[0]->getInternId());
        self::assertSame('Erster Text.', $result->getStatements()[0]->getText());

        self::assertTrue($result->hasWarnings());
        $warnings = $result->getWarningsAsArray();
        self::assertCount(1, $warnings);
        self::assertSame(3, $warnings[0]['lineNumber']);
        self::assertStringContainsString('DUP-001', $warnings[0]['message']);
    }

    /**
     * The importer is a shared, injected service and keeps its used-Eingangsnummer bookkeeping in a
     * property rather than a fresh local each call - so a second, unrelated process() call on the same
     * instance (e.g. a second import job picked up by the same worker) must not see rows accepted by an
     * earlier file, or it would wrongly skip them as duplicates.
     */
    public function testUsedInternIdsResetBetweenCallsOnTheSameSharedInstance(): void
    {
        $this->setProcedureAndLogin();

        $this->sut->process($this->fixture('duplicate_internid_in_file.csv'));
        $result = $this->sut->process($this->fixture('duplicate_internid_in_file.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(1, $result->getStatementCount());
        self::assertSame('DUP-001', $result->getStatements()[0]->getInternId());
    }

    public function testInternIdAlreadyUsedInProcedureIsSkippedWithWarning(): void
    {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        StatementFactory::createOne([
            'procedure' => $procedure,
            'internId'  => 'DUP-EXISTING',
        ]);

        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('duplicate_internid_in_db.csv'));

        self::assertFalse($result->hasErrors(), $this->describeErrors($result->getErrorsAsArray()));
        self::assertSame(0, $result->getStatementCount());

        self::assertTrue($result->hasWarnings());
        $warnings = $result->getWarningsAsArray();
        self::assertCount(1, $warnings);
        self::assertStringContainsString('DUP-EXISTING', $warnings[0]['message']);
    }

    public function testInvalidDateIsReportedWithItsLineNumber(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('invalid_date.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
        self::assertSame([2], array_unique(array_column($result->getErrorsAsArray(), 'lineNumber')));
    }

    public function testInvalidSubmitTypeIsReported(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('invalid_submit_type.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
    }

    public function testEmptyStatementTextIsReported(): void
    {
        $this->setProcedureAndLogin();

        $result = $this->sut->process($this->fixture('empty_text.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
    }

    private function setProcedureAndLogin(): void
    {
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    private function fixture(string $filename): SplFileInfo
    {
        $path = __DIR__.'/res/csv_statement_import/'.$filename;

        if (!file_exists($path)) {
            self::fail("Test file not found: {$path}");
        }

        return new SplFileInfo($path, '', $filename);
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    private function describeErrors(array $errors): string
    {
        return implode(
            "\n",
            array_map(
                static fn (array $error): string => sprintf('Zeile %s: %s', $error['lineNumber'], $error['message']),
                $errors
            )
        );
    }
}
