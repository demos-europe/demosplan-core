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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Tests for the elementImportDirToArray method in DocumentHandler.
 *
 * The extracted archive is read from local disk rather than from flysystem, so these tests build
 * real directory structures in a temporary directory instead of mocking a storage operator.
 */
class DocumentHandlerElementImportTest extends TestCase
{
    /**
     * @var DocumentHandler|MockObject
     */
    private $documentHandler;

    /**
     * @var Session|MockObject
     */
    private $session;

    /**
     * @var string
     */
    private $importDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = $this->createMock(Session::class);

        // Create a partial mock of DocumentHandler
        $this->documentHandler = $this->getMockBuilder(DocumentHandler::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSession'])
            ->getMock();

        // Set mocked session
        $this->documentHandler->method('getSession')
            ->willReturn($this->session);

        $this->importDir = sys_get_temp_dir().'/dplan-element-import-test-'.uniqid('', true);
        (new Filesystem())->mkdir($this->importDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->importDir);

        parent::tearDown();
    }

    /**
     * Creates the given relative paths below the import directory. Paths ending in a slash become
     * directories, everything else an empty file.
     */
    private function createStructure(array $relativePaths): void
    {
        $filesystem = new Filesystem();

        foreach ($relativePaths as $relativePath) {
            $absolutePath = $this->importDir.'/'.rtrim($relativePath, '/');

            if (str_ends_with($relativePath, '/')) {
                $filesystem->mkdir($absolutePath);
                continue;
            }

            $filesystem->mkdir(dirname($absolutePath));
            $filesystem->touch($absolutePath);
        }
    }

    private function invokeElementImportDirToArray(string $directory): array
    {
        $reflection = new ReflectionClass(DocumentHandler::class);
        $method = $reflection->getMethod('elementImportDirToArray');
        $method->setAccessible(true);

        return $method->invoke($this->documentHandler, $directory);
    }

    /**
     * Test that a simple file structure is correctly converted to an array.
     */
    public function testSimpleStructure(): void
    {
        $this->createStructure(['file.pdf']);

        $this->session->expects($this->once())
            ->method('set')
            ->with('bulkImportFilesTotal', 1);

        $result = $this->invokeElementImportDirToArray($this->importDir);

        // Assert the result structure
        $this->assertCount(1, $result);
        $this->assertFalse($result[0]['isDir']);
        $this->assertEquals('file.pdf', $result[0]['title']);
        $this->assertEquals($this->importDir.'/file.pdf', $result[0]['path']);
    }

    /**
     * Test that a nested directory structure is correctly converted to an array
     * without duplicating files at the root level.
     */
    public function testNestedStructure(): void
    {
        $this->createStructure([
            'root-file.pdf',
            'Ordner 2/Anlage 13/document1.pdf',
            'Ordner 2/Anlage 13/document2.pdf',
        ]);

        // Setup session mock for file count
        $this->session->expects($this->exactly(3))
            ->method('set')
            ->with('bulkImportFilesTotal', $this->anything());

        $result = $this->invokeElementImportDirToArray($this->importDir);

        // Assert the result has the correct structure
        $this->assertCount(2, $result);

        // Find elements by type
        $files = array_filter($result, fn ($item) => !$item['isDir']);
        $dirs = array_filter($result, fn ($item) => $item['isDir']);

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
     * Test that files are not duplicated at root level when they belong to subdirectories.
     */
    public function testNoDuplicatedFiles(): void
    {
        $this->createStructure([
            'Ordner 1/file1.pdf',
            'Ordner 1/file2.pdf',
            'Ordner 2/file3.pdf',
            'Ordner 2/file4.pdf',
        ]);

        // Session mock for counting files
        $this->session->expects($this->exactly(4))
            ->method('set')
            ->with('bulkImportFilesTotal', $this->anything());

        $result = $this->invokeElementImportDirToArray($this->importDir);

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
        $filesAtRoot = array_filter($result, fn ($item) => !$item['isDir']);
        $this->assertCount(0, $filesAtRoot);
    }

    /**
     * Test resolveImportFileName method with various scenarios.
     */
    #[DataProvider('resolveImportFileNameDataProvider')]
    public function testResolveImportFileName(
        array $entry,
        array $sessionElementImportList,
        array $request,
        string $expectedResult,
        string $testDescription,
    ): void {
        // Call the method using reflection
        $reflection = new ReflectionClass(DocumentHandler::class);
        $method = $reflection->getMethod('resolveImportFileName');
        $method->setAccessible(true);
        $result = $method->invoke($this->documentHandler, $entry, $sessionElementImportList, $request);

        // Assert expected result
        $this->assertEquals($expectedResult, $result, $testDescription);
    }

    /**
     * Data provider for testResolveImportFileName.
     */
    public static function resolveImportFileNameDataProvider(): array
    {
        $originalDocumentTitle = 'Original Document Name';
        $originalDocumentPath = 'tmp/import/123/original-document.pdf';
        $userAdjustedDocumentTitle = 'My Custom Document Name';

        return [
            'user_adjusted_name_found' => [
                'entry' => [
                    'title' => $originalDocumentTitle,
                    'path'  => $originalDocumentPath,
                ],
                'sessionElementImportList' => [
                    'file_12345' => '/'.$originalDocumentPath,
                    'file_67890' => '/tmp/import/123/other-file.pdf',
                ],
                'request' => [
                    'file_12345' => $userAdjustedDocumentTitle,
                    'file_67890' => 'Other Custom Name',
                ],
                'expectedResult'  => $userAdjustedDocumentTitle,
                'testDescription' => 'Should return user-adjusted name when available',
            ],

            'no_user_adjustment' => [
                'entry' => [
                    'title' => $originalDocumentTitle,
                    'path'  => $originalDocumentPath,
                ],
                'sessionElementImportList' => [
                    'file_67890' => '/tmp/import/123/other-file.pdf',
                ],
                'request' => [
                    'file_67890' => 'Other Custom Name',
                ],
                'expectedResult'  => $originalDocumentTitle,
                'testDescription' => 'Should return original name when no user adjustment exists',
            ],

            'empty_user_input' => [
                'entry' => [
                    'title' => $originalDocumentTitle,
                    'path'  => $originalDocumentPath,
                ],
                'sessionElementImportList' => [
                    'file_12345' => '/'.$originalDocumentPath,
                ],
                'request' => [
                    'file_12345' => '', // Empty user input
                ],
                'expectedResult'  => $originalDocumentTitle,
                'testDescription' => 'Should return original name when user provides empty string',
            ],
        ];
    }
}
