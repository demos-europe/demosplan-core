<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Unit;

use demosplan\DemosPlanCoreBundle\Logic\Document\DocumentHandler;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Tests for the elementImportDirToArray method in DocumentHandler
 */
class DocumentHandlerElementImportTest extends TestCase
{
    /**
     * @var DocumentHandler|MockObject
     */
    private $documentHandler;

    /**
     * @var FilesystemOperator|MockObject
     */
    private $defaultStorage;

    /**
     * @var Session|MockObject
     */
    private $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for required dependencies
        $this->defaultStorage = $this->createMock(FilesystemOperator::class);
        $messageBag = $this->createMock(MessageBagInterface::class);
        $this->session = $this->createMock(Session::class);

        // Create a partial mock of DocumentHandler
        $this->documentHandler = $this->getMockBuilder(DocumentHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSession'])
            ->getMock();

        // Set mocked session
        $this->documentHandler->method('getSession')
            ->willReturn($this->session);

        // Set the mocked defaultStorage using reflection
        $reflection = new ReflectionClass(DocumentHandler::class);
        $property = $reflection->getProperty('defaultStorage');
        $property->setAccessible(true);
        $property->setValue($this->documentHandler, $this->defaultStorage);
    }

    /**
     * Creates a mock DirectoryListing that iterates over the provided items
     */
    private function createMockListing(array $items): DirectoryListing
    {
        return new DirectoryListing($items);
    }

    /**
     * Test that a simple file structure is correctly converted to an array
     */
    public function testSimpleStructure(): void
    {
        // Create a simple structure with one file
        $directoryPath = 'tmp/import/123';
        $fileAttributes = new FileAttributes('tmp/import/123/file.pdf');

        // Setup mock behaviors
        $this->defaultStorage->expects($this->once())
            ->method('listContents')
            ->with($directoryPath, false)
            ->willReturn($this->createMockListing([$fileAttributes]));

        $this->session->expects($this->once())
            ->method('set')
            ->with('bulkImportFilesTotal', 1);

        // Call the method using reflection
        $reflection = new ReflectionClass(DocumentHandler::class);
        $method = $reflection->getMethod('elementImportDirToArray');
        $method->setAccessible(true);
        $result = $method->invoke($this->documentHandler, $directoryPath);

        // Assert the result structure
        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['isDir']);
        $this->assertEquals('file.pdf', $result[0]['title']);
        $this->assertEquals('tmp/import/123/file.pdf', $result[0]['path']);
    }

    /**
     * Test that a nested directory structure is correctly converted to an array
     * without duplicating files at the root level
     */
    public function testNestedStructure(): void
    {
        $baseDir = 'tmp/import/456';
        $nestedDir = 'tmp/import/456/Ordner 2';
        $nestedDir2 = 'tmp/import/456/Ordner 2/Anlage 13';

        // Create test directory structure
        $dirAttributes = new DirectoryAttributes($nestedDir);
        $fileAttributes1 = new FileAttributes('tmp/import/456/root-file.pdf');

        $this->defaultStorage->expects($this->exactly(3))
            ->method('listContents')
            ->willReturnCallback(function($path, $deep) use ($baseDir, $nestedDir, $nestedDir2, $dirAttributes, $fileAttributes1) {
                if ($path === $baseDir) {
                    return $this->createMockListing([$dirAttributes, $fileAttributes1]);
                }

                if ($path === $nestedDir) {
                    return $this->createMockListing([new DirectoryAttributes($nestedDir2)]);
                }

                if ($path === $nestedDir2) {
                    return $this->createMockListing([
                        new FileAttributes('tmp/import/456/Ordner 2/Anlage 13/document1.pdf'),
                        new FileAttributes('tmp/import/456/Ordner 2/Anlage 13/document2.pdf'),
                    ]);
                }
                return $this->createMockListing([]);
            });

        // Setup session mock for file count
        $this->session->expects($this->exactly(3))
            ->method('set')
            ->with('bulkImportFilesTotal', $this->anything());

        // Call the method using reflection
        $reflection = new ReflectionClass(DocumentHandler::class);
        $method = $reflection->getMethod('elementImportDirToArray');
        $method->setAccessible(true);
        $result = $method->invoke($this->documentHandler, $baseDir);

        // Assert the result has the correct structure
        $this->assertCount(2, $result);

        // Find elements by type
        $files = array_filter($result, fn($item) => !$item['isDir']);
        $dirs = array_filter($result, fn($item) => $item['isDir']);

        // Check that we have one file and one directory
        $this->assertCount(1, $files);
        $this->assertCount(1, $dirs);

        // Get the file and directory
        $rootFile = reset($files);
        $dir = reset($dirs);

        // Check file properties
        $this->assertEquals('root-file.pdf', $rootFile['title']);

        // Check directory properties
        $this->assertEquals('Ordner 2', $dir['title']);
        $this->assertArrayHasKey('entries', $dir);

        // Check nested directory
        $nestedDir = $dir['entries'][0];
        $this->assertTrue($nestedDir['isDir']);
        $this->assertEquals('Anlage 13', $nestedDir['title']);
        $this->assertArrayHasKey('entries', $nestedDir);

        // Check files in nested directory
        $this->assertCount(2, $nestedDir['entries']);
        $this->assertFalse($nestedDir['entries'][0]['isDir']);
        $this->assertFalse($nestedDir['entries'][1]['isDir']);
    }

    /**
     * Test that files are not duplicated at root level when they belong to subdirectories
     */
    public function testNoDuplicatedFiles(): void
    {
        $importDir = 'tmp/import/789';
        $folder1 = 'tmp/import/789/Ordner 1';
        $folder2 = 'tmp/import/789/Ordner 2';

        // Create complex structure mimicking the original issue
        $dir1Attributes = new DirectoryAttributes($folder1);
        $dir2Attributes = new DirectoryAttributes($folder2);

        // Setup directory structure
        $this->defaultStorage->expects($this->exactly(3))
            ->method('listContents')
            ->willReturnCallback(function($path, $deep) use ($importDir, $folder1, $folder2, $dir1Attributes, $dir2Attributes) {
                if ($path === $importDir) {
                    return $this->createMockListing([$dir1Attributes, $dir2Attributes]);
                }

                if ($path === $folder1) {
                    return $this->createMockListing([
                        new FileAttributes('tmp/import/789/Ordner 1/file1.pdf'),
                        new FileAttributes('tmp/import/789/Ordner 1/file2.pdf'),
                    ]);
                }

                if ($path === $folder2) {
                    return $this->createMockListing([
                        new FileAttributes('tmp/import/789/Ordner 2/file3.pdf'),
                        new FileAttributes('tmp/import/789/Ordner 2/file4.pdf'),
                    ]);
                }
                return $this->createMockListing([]);
            });

        // Session mock for counting files
        $this->session->expects($this->exactly(4))
            ->method('set')
            ->with('bulkImportFilesTotal', $this->anything());

        // Call the method using reflection
        $reflection = new ReflectionClass(DocumentHandler::class);
        $method = $reflection->getMethod('elementImportDirToArray');
        $method->setAccessible(true);
        $result = $method->invoke($this->documentHandler, $importDir);

        // Assert structure: should have exactly 2 directories at root
        $this->assertCount(2, $result);
        $this->assertTrue($result[0]['isDir']);
        $this->assertTrue($result[1]['isDir']);

        // Check first directory
        $dir1 = $result[0];
        $this->assertEquals('Ordner 1', $dir1['title']);
        $this->assertCount(2, $dir1['entries']);
        $this->assertEquals('file1.pdf', $dir1['entries'][0]['title']);
        $this->assertEquals('file2.pdf', $dir1['entries'][1]['title']);

        // Check second directory
        $dir2 = $result[1];
        $this->assertEquals('Ordner 2', $dir2['title']);
        $this->assertCount(2, $dir2['entries']);
        $this->assertEquals('file3.pdf', $dir2['entries'][0]['title']);
        $this->assertEquals('file4.pdf', $dir2['entries'][1]['title']);

        // Verify no files at root level
        $filesAtRoot = array_filter($result, fn($item) => !$item['isDir']);
        $this->assertCount(0, $filesAtRoot);
    }
}
