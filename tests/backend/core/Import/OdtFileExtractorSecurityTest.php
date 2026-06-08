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

use demosplan\DemosPlanCoreBundle\Tools\ODT\OdtFileExtractor;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class OdtFileExtractorSecurityTest extends TestCase
{
    private OdtFileExtractor $extractor;
    private string $tempDir;
    private string $maliciousZipPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new OdtFileExtractor();
        $this->tempDir = DemosPlanPath::getTemporaryPath('odt_extractor_security_test');
        $this->maliciousZipPath = $this->tempDir.'/malicious.odt';

        // Create temp directory
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (file_exists($this->maliciousZipPath)) {
            unlink($this->maliciousZipPath);
        }

        if (is_dir($this->tempDir)) {
            $this->recursiveDelete($this->tempDir);
        }

        parent::tearDown();
    }

    public function testExtractContentPreventsPathTraversalAttacks(): void
    {
        // Create a malicious ZIP file with path traversal attempts
        $this->createMaliciousZip();

        // Extract content - this should not throw exceptions or extract malicious files
        $result = $this->extractor->extractContent($this->maliciousZipPath);

        // Verify extraction completed without security violations
        $this->assertNotNull($result);
        $this->assertIsString($result->tempDir);

        // Verify that no files were extracted outside the intended directory
        $baseDir = dirname($result->tempDir);

        // Check that no malicious files exist in parent directories
        $this->assertFileDoesNotExist($baseDir.'/etc_passwd');
        $this->assertFileDoesNotExist($baseDir.'/sensitive_secret.txt');
        $this->assertFileDoesNotExist($baseDir.'/../malicious.txt');

        // Verify only legitimate files are in the extraction directory
        $extractedFiles = $this->getExtractedFiles($result->tempDir);

        // Should only contain legitimate ODT files, no path traversal files
        foreach ($extractedFiles as $file) {
            $this->assertStringNotContainsString('..', $file, 'No file paths should contain path traversal sequences');
            $this->assertStringStartsNotWith('/', $file, 'No file paths should be absolute');
        }

        // Clean up
        $this->extractor->cleanup($result->tempDir);
        $this->assertDirectoryDoesNotExist($result->tempDir);
    }

    public function testExtractContentHandlesNormalOdtFiles(): void
    {
        // Create a normal ODT-like ZIP file
        $this->createNormalZip();

        $result = $this->extractor->extractContent($this->maliciousZipPath);

        $this->assertNotNull($result);
        $this->assertIsString($result->tempDir);
        $this->assertDirectoryExists($result->tempDir);

        // Verify normal files are extracted
        $extractedFiles = $this->getExtractedFiles($result->tempDir);
        $this->assertContains('content.xml', $extractedFiles);
        $this->assertContains('styles.xml', $extractedFiles);
        $this->assertContains('Pictures/image1.png', $extractedFiles);

        // Clean up
        $this->extractor->cleanup($result->tempDir);
    }

    private function createMaliciousZip(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->maliciousZipPath, ZipArchive::CREATE);

        // Add legitimate ODT files
        $zip->addFromString('content.xml', '<office:document-content></office:document-content>');
        $zip->addFromString('styles.xml', '<office:document-styles></office:document-styles>');
        $zip->addFromString('META-INF/manifest.xml', '<manifest:manifest></manifest:manifest>');

        // Add malicious files with path traversal attempts
        $zip->addFromString('../../../etc_passwd', 'root:x:0:0:root:/root:/bin/bash');
        $zip->addFromString('../../../../sensitive_secret.txt', 'SECRET_API_KEY=12345');
        $zip->addFromString('/etc_passwd_absolute', 'malicious content');
        $zip->addFromString('normal/../../../malicious.txt', 'path traversal attack');

        $zip->close();
    }

    private function createNormalZip(): void
    {
        $zip = new ZipArchive();
        $zip->open($this->maliciousZipPath, ZipArchive::CREATE);

        // Add legitimate ODT files only
        $zip->addFromString('content.xml', '<office:document-content></office:document-content>');
        $zip->addFromString('styles.xml', '<office:document-styles></office:document-styles>');
        $zip->addFromString('META-INF/manifest.xml', '<manifest:manifest></manifest:manifest>');
        $zip->addFromString('Pictures/image1.png', 'fake-png-data');
        $zip->addFromString('Thumbnails/thumbnail.png', 'fake-thumbnail-data');

        $zip->close();
    }

    private function getExtractedFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $iterator->getSubPathName();
            }
        }

        return $files;
    }

    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }
}
