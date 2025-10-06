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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanCoreBundle\Tools\DocxImporterInterface;
use demosplan\DemosPlanCoreBundle\Tools\OdtImporter;
use demosplan\DemosPlanCoreBundle\Tools\PdfCreatorInterface;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use League\Flysystem\FilesystemOperator;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use ZipArchive;

class ServiceImporterSecurityTest extends TestCase
{
    private const ODT_MIME_TYPE = 'application/vnd.oasis.opendocument.text';

    private ServiceImporter $serviceImporter;
    private ReflectionMethod $isOdtFileMethod;
    private ReflectionMethod $validateOdtStructureMethod;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocked dependencies
        $docxImporter = $this->createMock(DocxImporterInterface::class);
        $odtImporter = $this->createMock(OdtImporter::class);
        $fileService = $this->createMock(FileService::class);
        $filesystem = $this->createMock(FilesystemOperator::class);
        $globalConfig = $this->createMock(GlobalConfigInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $messageBag = $this->createMock(MessageBagInterface::class);
        $paragraphRepository = $this->createMock(ParagraphRepository::class);
        $paragraphService = $this->createMock(ParagraphService::class);
        $pdfCreator = $this->createMock(PdfCreatorInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $rpcClient = $this->createMock(RpcClient::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->serviceImporter = new ServiceImporter(
            $docxImporter,
            $odtImporter,
            $fileService,
            $filesystem,
            $globalConfig,
            $logger,
            $messageBag,
            $paragraphRepository,
            $paragraphService,
            $pdfCreator,
            $router,
            $rpcClient,
            $eventDispatcher
        );

        // Make private methods accessible
        $reflection = new ReflectionClass($this->serviceImporter);
        $this->isOdtFileMethod = $reflection->getMethod('isOdtFile');
        $this->isOdtFileMethod->setAccessible(true);
        $this->validateOdtStructureMethod = $reflection->getMethod('validateOdtStructure');
        $this->validateOdtStructureMethod->setAccessible(true);

        // Create temp directory for test files
        $this->tempDir = DemosPlanPath::getTemporaryPath('service_importer_security_test');
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function testIsOdtFileAcceptsLegitimateOdtFiles(): void
    {
        // Create a legitimate ODT file
        $validOdtPath = $this->createValidOdtFile();
        $fileInfo = $this->createFileInfo($validOdtPath, 'test.odt', self::ODT_MIME_TYPE);

        // Debug: check if file exists and can be accessed
        $this->assertTrue(file_exists($validOdtPath), 'Test ODT file should exist');
        $this->assertNotNull($fileInfo->getAbsolutePath(), 'FileInfo should have absolute path');
        $this->assertEquals($validOdtPath, $fileInfo->getAbsolutePath(), 'Absolute paths should match');

        $file = new File($validOdtPath);
        $result = $this->isOdtFileMethod->invoke($this->serviceImporter, $fileInfo, $file);

        $this->assertTrue($result, 'Valid ODT file should be accepted');
    }

    public function testIsOdtFileRejectsFileWithOdtExtensionButInvalidContent(): void
    {
        // Create a file with ODT extension but invalid content
        $invalidFile = $this->tempDir.'/fake.odt';
        file_put_contents($invalidFile, 'This is not a valid ODT file');

        $fileInfo = $this->createFileInfo($invalidFile, 'fake.odt', 'text/plain');

        $file = new File($invalidFile);
        $result = $this->isOdtFileMethod->invoke($this->serviceImporter, $fileInfo, $file);

        $this->assertFalse($result, 'File with ODT extension but invalid content should be rejected');
    }

    public function testIsOdtFileRejectsFileWithCorrectMimeTypeButInvalidStructure(): void
    {
        // Create a file with correct MIME type but invalid ZIP structure
        $invalidFile = $this->tempDir.'/fake_mime.odt';
        file_put_contents($invalidFile, 'Invalid ZIP content');

        $fileInfo = $this->createFileInfo($invalidFile, 'fake_mime.odt', self::ODT_MIME_TYPE);

        $file = new File($invalidFile);
        $result = $this->isOdtFileMethod->invoke($this->serviceImporter, $fileInfo, $file);

        $this->assertFalse($result, 'File with correct MIME type but invalid structure should be rejected');
    }

    public function testIsOdtFileRejectsZipFileWithWrongMimetype(): void
    {
        // Create a ZIP file with wrong mimetype
        $zipFile = $this->tempDir.'/wrong_mimetype.odt';
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE);
        $zip->addFromString('mimetype', 'application/zip'); // Wrong mimetype
        $zip->addFromString('content.xml', '<?xml version="1.0"?><content></content>');
        $zip->close();

        $fileInfo = $this->createFileInfo($zipFile, 'wrong_mimetype.odt', self::ODT_MIME_TYPE);

        $file = new File($zipFile);
        $result = $this->isOdtFileMethod->invoke($this->serviceImporter, $fileInfo, $file);

        $this->assertFalse($result, 'ZIP file with wrong mimetype should be rejected');
    }

    public function testIsOdtFileRejectsZipFileWithoutMimetype(): void
    {
        // Create a ZIP file without mimetype file
        $zipFile = $this->tempDir.'/no_mimetype.odt';
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::CREATE);
        $zip->addFromString('content.xml', '<?xml version="1.0"?><content></content>');
        $zip->close();

        $fileInfo = $this->createFileInfo($zipFile, 'no_mimetype.odt', self::ODT_MIME_TYPE);

        $file = new File($zipFile);
        $result = $this->isOdtFileMethod->invoke($this->serviceImporter, $fileInfo, $file);

        $this->assertFalse($result, 'ZIP file without mimetype should be rejected');
    }

    public function testIsOdtFileRejectsExecutableFilesDisguisedAsOdt(): void
    {
        // Create an executable file with ODT extension
        $executableFile = $this->tempDir.'/malicious.odt';
        file_put_contents($executableFile, "#!/bin/bash\necho 'malicious code'");
        chmod($executableFile, 0755);

        $fileInfo = $this->createFileInfo($executableFile, 'malicious.odt', 'application/x-executable');

        $file = new File($executableFile);
        $result = $this->isOdtFileMethod->invoke($this->serviceImporter, $fileInfo, $file);

        $this->assertFalse($result, 'Executable file with ODT extension should be rejected');
    }

    public function testIsOdtFileRejectsNonExistentFile(): void
    {
        $fileInfo = $this->createFileInfo('/nonexistent/file.odt', 'nonexistent.odt', self::ODT_MIME_TYPE);

        // Create a dummy file for the non-existent case
        $dummyFile = $this->tempDir.'/dummy.odt';
        file_put_contents($dummyFile, 'dummy');
        $file = new File($dummyFile);
        $result = $this->isOdtFileMethod->invoke($this->serviceImporter, $fileInfo, $file);

        $this->assertFalse($result, 'Non-existent file should be rejected');
    }

    public function testValidateOdtStructureRejectsNonZipFile(): void
    {
        $textFile = $this->tempDir.'/text.odt';
        file_put_contents($textFile, 'This is plain text');

        $result = $this->validateOdtStructureMethod->invoke($this->serviceImporter, $textFile);

        $this->assertFalse($result, 'Non-ZIP file should be rejected');
    }

    public function testValidateOdtStructureRejectsCorruptedZipFile(): void
    {
        $corruptedZip = $this->tempDir.'/corrupted.odt';
        file_put_contents($corruptedZip, 'PK'.random_bytes(100)); // Corrupted ZIP signature

        $result = $this->validateOdtStructureMethod->invoke($this->serviceImporter, $corruptedZip);

        $this->assertFalse($result, 'Corrupted ZIP file should be rejected');
    }

    private function createValidOdtFile(): string
    {
        $odtFile = $this->tempDir.'/valid.odt';
        $zip = new ZipArchive();
        $zip->open($odtFile, ZipArchive::CREATE);

        // Add correct mimetype
        $zip->addFromString('mimetype', self::ODT_MIME_TYPE);

        // Add minimal content.xml
        $contentXml = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0">
    <office:body>
        <office:text>
            <text:p xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">Test content</text:p>
        </office:text>
    </office:body>
</office:document-content>';
        $zip->addFromString('content.xml', $contentXml);

        // Add minimal META-INF/manifest.xml
        $manifestXml = '<?xml version="1.0" encoding="UTF-8"?>
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0">
    <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
    <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
</manifest:manifest>';
        $zip->addEmptyDir('META-INF');
        $zip->addFromString('META-INF/manifest.xml', $manifestXml);

        $zip->close();

        return $odtFile;
    }

    private function createFileInfo(string $absolutePath, string $fileName, string $contentType): FileInfo
    {
        return new FileInfo(
            file_exists($absolutePath) ? hash_file('md5', $absolutePath) : 'dummy_hash',
            $fileName,
            file_exists($absolutePath) ? filesize($absolutePath) : 0,
            $contentType,
            dirname($absolutePath),
            $absolutePath,
            null
        );
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
}
