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

use demosplan\DemosPlanCoreBundle\Tools\ODT\OdtElementProcessor;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class OdtElementProcessorSecurityTest extends TestCase
{
    private OdtElementProcessor $processor;
    private string $tempDir;
    private ReflectionMethod $getImageDataMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new OdtElementProcessor();
        $this->tempDir = DemosPlanPath::getTemporaryPath('odt_processor_security_test');

        // Create temp directory
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        // Initialize processor with empty style maps
        $this->processor->initialize([], [], [], $this->tempDir);

        // Make getImageData method accessible for testing
        $reflection = new ReflectionClass($this->processor);
        $this->getImageDataMethod = $reflection->getMethod('getImageData');
        $this->getImageDataMethod->setAccessible(true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function testGetImageDataBlocksPathTraversalAttacks(): void
    {
        // Create a legitimate image file in temp directory
        $legitimateImagePath = $this->tempDir.'/legitimate.png';
        file_put_contents($legitimateImagePath, 'fake image data');

        // Create a sensitive file outside temp directory
        $sensitiveDir = dirname($this->tempDir).'/sensitive';
        if (!is_dir($sensitiveDir)) {
            mkdir($sensitiveDir, 0755, true);
        }
        $sensitiveFile = $sensitiveDir.'/secret.txt';
        file_put_contents($sensitiveFile, 'sensitive data');

        // Test legitimate access works
        $result = $this->getImageDataMethod->invoke($this->processor, 'legitimate.png');
        $this->assertSame('fake image data', $result, 'Legitimate file access should work');

        // Test path traversal attempts are blocked
        $pathTraversalAttempts = [
            '../sensitive/secret.txt',
            '../../sensitive/secret.txt',
            '../../../etc/passwd',
            '..\\sensitive\\secret.txt', // Windows-style
            './.././sensitive/secret.txt',
            'subdir/../../../sensitive/secret.txt',
        ];

        foreach ($pathTraversalAttempts as $maliciousPath) {
            $result = $this->getImageDataMethod->invoke($this->processor, $maliciousPath);
            $this->assertNull($result, "Path traversal attempt '$maliciousPath' should be blocked");
        }

        // Test absolute path attempts are blocked
        $result = $this->getImageDataMethod->invoke($this->processor, $sensitiveFile);
        $this->assertNull($result, 'Absolute path access should be blocked');

        // Clean up sensitive file
        unlink($sensitiveFile);
        rmdir($sensitiveDir);
    }

    public function testGetImageDataReturnsNullForNonExistentFiles(): void
    {
        $result = $this->getImageDataMethod->invoke($this->processor, 'nonexistent.png');
        $this->assertNull($result, 'Non-existent file should return null');
    }

    public function testGetImageDataHandlesSymbolicLinks(): void
    {
        // Create a file outside temp directory
        $externalDir = dirname($this->tempDir).'/external';
        if (!is_dir($externalDir)) {
            mkdir($externalDir, 0755, true);
        }
        $externalFile = $externalDir.'/external.txt';
        file_put_contents($externalFile, 'external data');

        // Create a symbolic link inside temp directory pointing outside
        $symlinkPath = $this->tempDir.'/symlink.txt';
        if (function_exists('symlink') && !$this->isWindows()) {
            symlink($externalFile, $symlinkPath);

            // Test that symbolic links pointing outside are blocked
            $result = $this->getImageDataMethod->invoke($this->processor, 'symlink.txt');
            $this->assertNull($result, 'Symbolic link pointing outside temp directory should be blocked');

            unlink($symlinkPath);
        }

        // Clean up
        unlink($externalFile);
        rmdir($externalDir);
    }

    public function testProcessImageWithPathTraversalInDocument(): void
    {
        // Create ODT content with malicious image reference
        $contentXml = '<?xml version="1.0" encoding="UTF-8"?>
        <office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
                                xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
                                xmlns:xlink="http://www.w3.org/1999/xlink">
            <office:body>
                <office:text>
                    <text:p xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
                        <draw:frame>
                            <draw:image xlink:href="../../../etc/passwd"/>
                        </draw:frame>
                    </text:p>
                </office:text>
            </office:body>
        </office:document-content>';

        $dom = new DOMDocument();
        $dom->loadXML($contentXml);

        // Process the document
        $result = $this->processor->processContent($dom);

        // Should not contain any image content since path traversal was blocked
        $this->assertStringNotContainsString('<img', $result, 'Malicious image should not be processed');
        $this->assertStringNotContainsString('data:', $result, 'No base64 data should be present');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function isWindows(): bool
    {
        return 'WIN' === strtoupper(substr(PHP_OS, 0, 3));
    }
}
