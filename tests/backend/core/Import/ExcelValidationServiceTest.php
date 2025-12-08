<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelValidationService;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Base\FunctionalTestCase;

class ExcelValidationServiceTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ExcelValidationService::class);
    }

    public function testValidExcelFile(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('valid_segment_import.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertFalse($result->hasErrors(), 'Valid file should have no errors');
        self::assertSame(0, $result->getErrorCount());
    }

    public function testMissingSegmentsWorksheet(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('missing_segments_worksheet.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());
        self::assertGreaterThan(0, $result->getErrorCount());

        $errors = $result->getErrors();
        $errorMessages = array_column($errors, 'message');
        self::assertContains(
            'Arbeitsblatt "Abschnitte" nicht gefunden',
            $errorMessages
        );
    }

    public function testMissingMetadataWorksheet(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('missing_metadata_worksheet.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());
        self::assertGreaterThan(0, $result->getErrorCount());

        $errors = $result->getErrors();
        $errorMessages = array_column($errors, 'message');
        self::assertContains(
            'Arbeitsblatt "Metadaten" nicht gefunden',
            $errorMessages
        );
    }

    public function testDuplicateInternId(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('duplicate_intern_id.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        $errorMessages = implode(' ', array_column($errors, 'message'));
        self::assertStringContainsString('Doppelter Abschnitt Interner ID', $errorMessages);
    }

    public function testStatementWithoutSegments(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('statement_without_segments.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        $errorMessages = implode(' ', array_column($errors, 'message'));
        self::assertStringContainsString('hat keine zugehörigen Abschnitte', $errorMessages);
    }

    public function testSegmentsWithoutStatement(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('segments_without_statement.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        $errorMessages = implode(' ', array_column($errors, 'message'));
        self::assertStringContainsString('gefunden, aber keine Metadaten', $errorMessages);
    }

    public function testInvalidEmail(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('invalid_email.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        $errorMessages = implode(' ', array_column($errors, 'message'));
        self::assertStringContainsString('Ungültige E-Mail-Adresse', $errorMessages);
    }

    public function testLengthViolations(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('length_violations.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());
        self::assertGreaterThan(0, $result->getErrorCount());
    }

    public function testEmptyStatementIdInSegment(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('empty_statement_id.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        $errorMessages = implode(' ', array_column($errors, 'message'));
        self::assertStringContainsString('Stellungnahme ID darf nicht leer sein', $errorMessages);
    }

    public function testEmptyRowsAreSkipped(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('empty_rows.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert - Empty rows should be skipped, so file should be valid
        self::assertFalse($result->hasErrors(), 'Empty rows should be skipped');
    }

    public function testPublicStatementTypeMapping(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('statement_types.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert - File should be valid (type mapping is internal logic)
        self::assertFalse($result->hasErrors());
    }

    public function testMultipleValidationErrors(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('multiple_errors.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());
        self::assertGreaterThanOrEqual(2, $result->getErrorCount(), 'Should have multiple errors');
    }

    public function testLargeFilePerformance(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('large_import.xlsx');
        $startTime = microtime(true);

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);
        $duration = microtime(true) - $startTime;

        // Assert - Should complete in reasonable time (< 10 seconds for large file)
        self::assertLessThan(10.0, $duration, 'Validation should be reasonably fast');

        // File should be valid
        self::assertFalse($result->hasErrors());
    }

    public function testCorruptExcelFile(): void
    {
        // Arrange
        $fileInfo = $this->createSplFileInfo('corrupt_file.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        $errorMessages = implode(' ', array_column($errors, 'message'));
        self::assertStringContainsString('Fehler beim Lesen der Excel-Datei', $errorMessages);
    }

    public function testSegmentCountValidation(): void
    {
        // Arrange - Statement with segmentCount = 0 (should fail)
        $fileInfo = $this->createSplFileInfo('zero_segment_count.xlsx');

        // Act
        $result = $this->sut->validateExcelFile($fileInfo);

        // Assert
        self::assertTrue($result->hasErrors());

        $errors = $result->getErrors();
        $errorMessages = implode(' ', array_column($errors, 'message'));
        // Cross-validation catches this before DTO validation
        self::assertStringContainsString('hat keine zugehörigen Abschnitte', $errorMessages);
    }

    /**
     * Helper method to create SplFileInfo from test resource file.
     */
    private function createSplFileInfo(string $filename): SplFileInfo
    {
        $path = __DIR__.'/res/excel_validation/'.$filename;

        if (!file_exists($path)) {
            self::fail("Test file not found: {$path}");
        }

        return new SplFileInfo($path, '', $filename);
    }
}
