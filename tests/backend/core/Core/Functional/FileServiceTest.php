<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Base\FunctionalTestCase;
use TypeError;

class FileServiceTest extends FunctionalTestCase
{
    /**
     * @var FileService
     */
    protected $sut;

    /**
     * @var File
     */
    protected $testFile;

    protected function setUp(): void
    {
        parent::setUp();

        // get testdata:
        $this->testFile = $this->fixtures->getReference('testFile');

        $this->sut = self::getContainer()->get(FileService::class);
    }

    public function testGetFileInfo()
    {
        $this->sut->getFileInfo($this->testFile->getIdent());
        static::assertTrue(true);
    }

    public function testNotExistantGetFileInfo()
    {
        $this->expectException(Exception::class);
        $this->sut->getFileInfo('I do not exist');
    }

    public function testGetNoImageImage()
    {
        $imagestring = $this->sut->getNotFoundImagePath();
        static::assertTrue(is_string($imagestring));
        static::assertFileExists($imagestring);
    }

    public function testSaveFileFromTemporaryFile()
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $originalFilename = 'Testfilename';

        // write File to test existance
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/test.txt', 'file1');
        static::assertFileExists($cacheDir.'/test.txt');

        $fileSize = filesize($cacheDir.'/test.txt');
        $mimeType = mime_content_type($cacheDir.'/test.txt');

        // Test function
        $fileIdent = $this->sut->saveTemporaryLocalFile($cacheDir.'/test.txt', $originalFilename)->getId();
        static::assertTrue(is_string($fileIdent));

        // Test DB-Entry
        $fileInfo = $this->sut->getFileInfo($fileIdent);
        static::assertEquals($originalFilename, $fileInfo->getFileName());
        static::assertEquals('text/plain', $fileInfo->getContentType());
        static::assertEquals($fileSize, $fileInfo->getFileSize());

        // Test original temporary file has moved
        static::assertFileDoesNotExist($cacheDir.'/test.txt');

        // Test Filestring

        $fileString = $this->sut->getFileString();
        static::assertEquals($originalFilename.':'.$fileIdent.':'.$fileSize.':'.$mimeType, $fileString);
    }

    public function testSaveFileFromUploadedFile(): void
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fileName = 'test2.txt';

        // write File to test existance
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/'.$fileName, 'file2');
        static::assertFileExists($cacheDir.'/'.$fileName);

        $fileSize = filesize($cacheDir.'/'.$fileName);
        $mimeType = mime_content_type($cacheDir.'/'.$fileName);
        $file = new UploadedFile(
            $cacheDir.'/'.$fileName,
            $fileName,
            'text/plain',
            filesize($cacheDir.'/'.$fileName),
            UPLOAD_ERR_OK,
            true
        );

        // Test function
        $fileId = $this->sut->saveUploadedFile($file)->getId();
        static::assertIsString($fileId);

        // Test DB-Entry
        $fileEntity = $this->sut->get($fileId);
        static::assertEquals($fileName, $fileEntity->getFileName());
        static::assertEquals('text/plain', $fileEntity->getMimetype());
        static::assertEquals('file2', $this->sut->getContent($fileEntity));

        // Test original temporary file has moved
        static::assertFileDoesNotExist($cacheDir.'/'.$fileName);

        // Test Filestring
        $fileString = $this->sut->getFileString();
        static::assertEquals($fileName.':'.$fileId.':'.$fileSize.':'.$mimeType, $fileString);
    }

    public function testSaveFileFromTemporaryFileWithUserid()
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $originalFilename = 'Testfilename';

        // write File to test existance
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/test.txt', 'file1');
        static::assertFileExists($cacheDir.'/test.txt');

        $fileSize = filesize($cacheDir.'/test.txt');
        $mimeType = mime_content_type($cacheDir.'/test.txt');

        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE);

        // Test function
        $author = 'SomeRandomUserId';
        $fileIdent = $this->sut->saveTemporaryLocalFile($cacheDir.'/test.txt', $originalFilename, $author, $procedure->getId())->getId();
        static::assertIsString($fileIdent);

        // Test DB-Entry
        $file = $this->sut->get($fileIdent);
        static::assertEquals($originalFilename, $file->getFileName());
        static::assertEquals('text/plain', $file->getMimetype());
        static::assertEquals($author, $file->getAuthor());

        // Test original temporary file has moved
        static::assertFileDoesNotExist($cacheDir.'/test.txt');

        // Test Filestring
        $fileString = $this->sut->getFileString();
        static::assertEquals($originalFilename.':'.$fileIdent.':'.$fileSize.':'.$mimeType, $fileString);

        static::assertEquals($procedure, $file->getProcedure());
    }

    public function testSaveFileFromUploadedFileWithUserId()
    {
        $globalConfig = self::getContainer()->get(GlobalConfigInterface::class);
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fileName = 'test2.txt';

        // write File to test existance
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/'.$fileName, 'file2');
        static::assertFileExists($cacheDir.'/'.$fileName);

        $fileSize = filesize($cacheDir.'/'.$fileName);
        $mimeType = mime_content_type($cacheDir.'/'.$fileName);
        $file = new UploadedFile(
            $cacheDir.'/'.$fileName,
            $fileName,
            'text/plain',
            filesize($cacheDir.'/'.$fileName),
            UPLOAD_ERR_OK,
            true
        );

        // Test function
        $fileId = $this->sut->saveUploadedFile($file, 'SomeRandomUserId')->getId();
        static::assertTrue(is_string($fileId));

        // Test DB-Entry
        $fileEntity = $this->sut->get($fileId);
        static::assertEquals($fileName, $fileEntity->getFileName());
        static::assertEquals('text/plain', $fileEntity->getMimetype());
        static::assertEquals('file2', $this->sut->getContent($fileEntity));

        // Test original temporary file has moved
        static::assertFileDoesNotExist($cacheDir.'/'.$fileName);

        // Test Filestring
        $fileString = $this->sut->getFileString();
        static::assertEquals($fileName.':'.$fileId.':'.$fileSize.':'.$mimeType, $fileString);
    }

    public function testSaveTemporaryFileEmpty()
    {
        $this->expectException(Exception::class);
        // Test function
        $this->sut->saveTemporaryLocalFile('', '');
    }

    public function testSaveTemporaryFileNull()
    {
        $this->expectException(TypeError::class);
        // Test function
        $this->sut->saveTemporaryLocalFile(null, '');
    }

    public function testMimeTypeNotAllowed()
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fileName = 'test3.txt';

        // write File to test existance
        $fs = new Filesystem();
        $tmpFilePath = $cacheDir.'/'.$fileName;
        $fs->dumpFile($tmpFilePath, 'file3');
        static::assertFileExists($tmpFilePath);

        $uploadedFileMock = $this->createMock(UploadedFile::class);

        // Configure the mock to return specific values
        $uploadedFileMock->method('getClientOriginalName')->willReturn($tmpFilePath);
        $uploadedFileMock->method('getMimeType')->willReturn('application/x-msdownload');
        $uploadedFileMock->method('getSize')->willReturn(filesize($tmpFilePath));
        $uploadedFileMock->method('getPathname')->willReturn($tmpFilePath);

        // Test function
        try {
            $this->sut->saveUploadedFile($uploadedFileMock);
            $this->fail('Exception should have been thrown');
        } catch (FileException $e) {
            static::assertEquals(20, $e->getCode());
            // Test original temporary file has moved
            static::assertFileDoesNotExist($tmpFilePath);
        }
    }

    public function testMimeTypeNotAllowedTemporaryFile()
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fileName = 'testfile.php';

        // write File to test existance
        // local file only, no need for flysystem
        $fs = new Filesystem();
        // copy this phpfile to generate a not allowed file
        $fs->copy(__FILE__, $cacheDir.'/'.$fileName);
        static::assertFileExists($cacheDir.'/'.$fileName);

        // Test function
        try {
            $this->sut->saveTemporaryLocalFile($cacheDir.'/'.$fileName, $fileName);
            $this->fail('Exception should have been thrown');
        } catch (FileException $e) {
            static::assertEquals(20, $e->getCode());
            // Test original temporary file has moved
            static::assertFileDoesNotExist($cacheDir.'/'.$fileName);
        }
    }

    public function testGetInvalidFilename()
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fileName = 'test%file.txt';

        // write File to test existence
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/'.$fileName, 'file3');
        static::assertFileExists($cacheDir.'/'.$fileName);

        $fileId = $this->sut->saveTemporaryLocalFile($cacheDir.'/'.$fileName, $fileName)->getId();
        $file = $this->sut->getFileInfo($fileId);
        static::assertEquals('testfile.txt', $file->getFileName());
    }

    /**
     * @throws Exception
     */
    public function testAddFile(): void
    {
        // build mock file to be saved
        $file = new File();
        $id = '61fb0d7f316753bd676459b6a7f6a95b';
        $file->setIdent($id);
        $file->setHash($id);
        $file->setMimetype('application/pdf');
        $file->setName('begruendung.pdf');
        $file->setFilename('begruendung.pdf');
        $file->setSize(1234);
        $file->setInfected(false);

        $result = $this->sut->addFile($file);
        static::assertInstanceOf(File::class, $result);
        $fileInfo = $this->sut->getFileInfo($result->getId());

        static::assertEquals($result->getId(), $fileInfo->getHash());
        static::assertSame($id, $result->getHash());
        static::assertSame('application/pdf', $result->getMimetype());
        static::assertSame('begruendung.pdf', $result->getName());
        static::assertSame('begruendung.pdf', $result->getFilename());
        static::assertSame(1234, $result->getSize());
        static::assertFalse($result->getInfected());
    }

    public function testGetFileContainerMultipleFiles()
    {
        $entityFiles = $this->sut->getEntityFiles('demosplan\DemosPlanCoreBundle\Entity\Statement\Statement', 'statementId', 'file');

        static::assertCount(2, $entityFiles);
        static::assertInstanceOf('demosplan\DemosPlanCoreBundle\Entity\File', $entityFiles[0]);
    }

    public function testGetFileContainerMultipleFileString()
    {
        $entityFiles = $this->sut->getEntityFileString('demosplan\DemosPlanCoreBundle\Entity\Statement\Statement', 'statementId', 'file');

        static::assertCount(2, $entityFiles);
        static::assertEquals('fileName:Hash:12534:image/png', $entityFiles[0]);
    }

    public function testSetFileContainerFileString()
    {
        $fileContainer = $this->sut->addStatementFileContainer(
            'statementId',
            $this->testFile->getId(),
            'fileString'
        );

        static::assertInstanceOf(FileContainer::class, $fileContainer);
        static::assertSame('statementId', $fileContainer->getEntityId());
        static::assertSame(Statement::class, $fileContainer->getEntityClass());
        static::assertSame('file', $fileContainer->getEntityField());
        static::assertSame('fileString', $fileContainer->getFileString());
        static::assertEquals($this->testFile, $fileContainer->getFile());
    }

    public function testSetFileContainerFileStringFailure()
    {
        $fileContainer = $this->sut->addStatementFileContainer(
            'statementId',
            null,
            ''
        );

        static::assertNull($fileContainer);
    }

    public function testDeleteFileContainer()
    {
        $fileContainer = $this->sut->addStatementFileContainer(
            'statementId',
            $this->testFile->getId(),
            'fileString'
        );
        static::assertInstanceOf(FileContainer::class, $fileContainer);

        $this->sut->deleteFileContainer($this->testFile->getId(), 'statementId');
    }

    public function testDeleteFileById()
    {
        $fileToDelete = $this->sut->getFileById($this->testFile->getId());
        static::assertInstanceOf(File::class, $fileToDelete);

        $this->sut->deleteFile($this->testFile->getId());

        $deletedFile = $this->sut->getFileById($this->testFile->getId());
        static::assertNull($deletedFile);
    }

    public function testDeleteFileByHash()
    {
        $fileToDelete = $this->sut->getFileById($this->testFile->getId());
        static::assertInstanceOf(File::class, $fileToDelete);

        $this->sut->deleteFile($this->testFile->getHash());

        $deletedFile = $this->sut->getFileById($this->testFile->getId());
        static::assertNull($deletedFile);
    }

    public function testGetAllFiles(): void
    {
        $allFiles = $this->sut->getAllFiles();
        static::assertCount(14, $allFiles);
    }

    public function testGetFile(): void
    {
        $file = $this->sut->get($this->testFile->getId());
        static::assertEquals($this->testFile->getId(), $file->getId());
    }

    public function testCopyFile(): void
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/test.txt', 'file1');
        $id = $this->sut->saveTemporaryLocalFile($cacheDir.'/test.txt', 'Testfilename')->getId();
        $testFile = $this->sut->get($id);

        $copiedFileId = $this->sut->copy($id)->getId();

        $copiedFile = $this->sut->get($copiedFileId);
        // Test File exists
        static::assertInstanceOf(File::class, $copiedFile);

        // Test original file has not been removed
        $originalTestFile = $this->sut->get($id);
        static::assertInstanceOf(File::class, $originalTestFile);

        static::assertNotEquals($id, $copiedFileId);
        static::assertEquals(
            $this->sut->getContent($testFile),
            $this->sut->getContent($copiedFile)
        );
    }

    public function testCopyFileByFileString()
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $fs = new Filesystem();
        $fs->dumpFile($cacheDir.'/test.txt', 'file1');
        $fileIdent = $this->sut->saveTemporaryLocalFile($cacheDir.'/test.txt', 'Testfilename')->getId();
        $testFile = $this->sut->get($fileIdent);

        $copiedFileIdent = $this->sut->copyByFileString($this->sut->getFileString())->getId();

        $copiedFile = $this->sut->get($copiedFileIdent);

        static::assertNotEquals($fileIdent, $copiedFileIdent);
        static::assertEquals(
            $this->sut->getContent($testFile),
            $this->sut->getContent($copiedFile)
        );
    }

    public function testSaveBinaryFileContent(): void
    {
        $fileName = 'testContent.txt';
        $fileContent = 'This is test file content';

        // Test function without prefix
        $file = $this->sut->saveBinaryFileContent($fileName, $fileContent);

        $fileId = $file->getId();
        static::assertIsString($fileId);

        // Test DB-Entry
        $savedFile = $this->sut->get($fileId);
        static::assertSame($fileName, $savedFile->getFileName());
        static::assertSame('text/plain', $savedFile->getMimetype());
        static::assertSame(strlen($fileContent), $savedFile->getSize());

        // Test file content
        $retrievedContent = $this->sut->getContent($savedFile);
        static::assertEquals($fileContent, $retrievedContent);

        // Test Filestring
        $fileString = $file->getFileString();
        static::assertStringContainsString($fileName, $fileString);
        static::assertStringContainsString($fileId, $fileString);
        static::assertStringContainsString((string) strlen($fileContent), $fileString);
    }

    public function testSaveBinaryFileContentWithPrefix(): void
    {
        $fileName = 'testWithPrefix.txt';
        $fileContent = 'This is test file content with prefix';
        $filenamePrefix = 'xbeteiligung';

        // Test function with prefix
        $file = $this->sut->saveBinaryFileContent($fileName, $fileContent, $filenamePrefix);

        // Test file content matches
        $retrievedContent = $this->sut->getContent($file);
        static::assertEquals($fileContent, $retrievedContent);
    }

    public function testSaveBinaryFileContentWithBinaryData(): void
    {
        $fileName = 'binaryTest.bin';
        // Create binary content (e.g., simulating decoded base64)
        $fileContent = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01";

        // Test function
        $file = $this->sut->saveBinaryFileContent($fileName, $fileContent);

        // Test file content matches exactly
        $retrievedContent = $this->sut->getContent($file);
        static::assertEquals($fileContent, $retrievedContent);
        static::assertSame(strlen($fileContent), $file->getSize());
    }

    public function testSaveFileContentEmptyContent(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File content cannot be empty');

        $this->sut->saveBinaryFileContent('test.txt', '');
    }

    public function testSaveFileContentFromXBeteiligungBase64(): void
    {
        // Base64 string from XBeteiligung XML message (PDF file)
        $base64Content = 'JVBERi0xLjQKJcfsj6IKNSAwIG9iago8PC9MZW5ndGggNiAwIFIvRmlsdGVyIC9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nGVSy27VQAwVFGjJRbzKB2THBKlTe2zP2NtKCAmx4Sq7llURXV2klv+XsCc3ohHKxuOcsc9j7kfIII2s0gjxbU63h+Fy38a7P0P/N+6/HIuHu+F+wJa5lNYbj+vbw3g1+0UdWxZU5nH+NeACG5UzSsBJm43zYbhOT6YLzFiKUXo6XUBmQq4tnURtYFI0Peu1ijFvMM+jpqYkJb3oY6CBrW3UUtPp5Gg2bJTOOqIB1/QyylrUxMElA4PydsKP+euAhpmVnfj804ne/A4AWEHd1LtYh8ToCl5NmAm8n24eNoiShRBKXFwR/7C+mXxTSO0kQJXpSOJyj24c5mJc/3eyWnWTVyOdPrVqi0cCFFJOQh9wscVFTwkYF4tcNba+0u0SVkqnZz6jokFzi1y8+TifFoxc3CJaqpZ1lha/tDvy1A1Nyg3FI/42+LKP/ewWxXn+dJ1eeyxKRp0ghA1aMXhjthLlm06KTXvM7h6LR/R2sdpzY6cQCqigdIZFFCi98yYWII7/Eaea+n1/PYKimt47UhDx+NbINzRbl2n1B7OAXZml824jcxXcwD9M0fbg7DGJXegwafUY3Od5+O7fX9A3oP1lbmRzdHJlYW0KZW5kb2JqCjYgMCBvYmoKNDUwCmVuZG9iago0IDAgb2JqCjw8L1R5cGUvUGFnZS9NZWRpYUJveCBbMCAwIDYxMiA3OTJdCi9Sb3RhdGUgMC9QYXJlbnQgMyAwIFIKL1Jlc291cmNlczw8L1Byb2NTZXRbL1BERiAvVGV4dF0KL0V4dEdTdGF0ZSAxMiAwIFIKL0ZvbnQgMTMgMCBSCj4+Ci9Db250ZW50cyA1IDAgUgo+PgplbmRvYmoKMyAwIG9iago8PCAvVHlwZSAvUGFnZXMgL0tpZHMgWwo0IDAgUgpdIC9Db3VudCAxCj4+CmVuZG9iagoxIDAgb2JqCjw8L1R5cGUgL0NhdGFsb2cgL1BhZ2VzIDMgMCBSCi9NZXRhZGF0YSAxOCAwIFIKPj4KZW5kb2JqCjcgMCBvYmoKPDwvVHlwZS9FeHRHU3RhdGUKL09QTSAxPj5lbmRvYmoKMTIgMCBvYmoKPDwvUjcKNyAwIFI+PgplbmRvYmoKMTMgMCBvYmoKPDwvUjgKOCAwIFIvUjEwCjEwIDAgUj4+CmVuZG9iago4IDAgb2JqCjw8L0Jhc2VGb250L0pCRUVJRitWZXJkYW5hL0ZvbnREZXNjcmlwdG9yIDkgMCBSL1R5cGUvRm9udAovRmlyc3RDaGFyIDEvTGFzdENoYXIgMjAvV2lkdGhzWyA2ODQgNTk2IDI3NCAzOTQgMzUyIDYzNiA1OTIgNjA3IDYzMyA2MzYgMzY0IDYzNiA2MzYgNzUxIDQyNwo1MjEgNjMzIDYyMyA2MTYgNTkyXQovRW5jb2RpbmcgMTYgMCBSL1N1YnR5cGUvVHJ1ZVR5cGU+PgplbmRvYmoKMTYgMCBvYmoKPDwvVHlwZS9FbmNvZGluZy9CYXNlRW5jb2RpbmcvV2luQW5zaUVuY29kaW5nL0RpZmZlcmVuY2VzWwoxL1MvZS9pL3Qvc3BhY2Uvb25lL3Yvby9uL3R3by9wZXJpb2QvemVyby9laWdodC9IL3IvcwovaC9kL1QveF0+PgplbmRvYmoKMTAgMCBvYmoKPDwvQmFzZUZvbnQvUlhSRVRIK1ZlcmRhbmEsQm9sZC9Gb250RGVzY3JpcHRvciAxMSAwIFIvVHlwZS9Gb250Ci9GaXJzdENoYXIgMS9MYXN0Q2hhciAxMS9XaWR0aHNbIDY4MiA2NjQgNTkzIDQ1NiA2OTkgNjg3IDY3MSA3MTIgMTA1OCA3MTIgMzQyXQovRW5jb2RpbmcgMTcgMCBSL1N1YnR5cGUvVHJ1ZVR5cGU+PgplbmRvYmoKMTcgMCBvYmoKPDwvVHlwZS9FbmNvZGluZy9CYXNlRW5jb2RpbmcvV2luQW5zaUVuY29kaW5nL0RpZmZlcmVuY2VzWwoxL1QvZS9zL3QvZC9vL2svdS9tL24vc3BhY2VdPj4KZW5kb2JqCjkgMCBvYmoKPDwvVHlwZS9Gb250RGVzY3JpcHRvci9Gb250TmFtZS9KQkVFSUYrVmVyZGFuYS9Gb250QkJveFswIC0xNiA4NzUgNzU5XS9GbGFncyAxMzEwNzYKL0FzY2VudCA3NTkKL0NhcEhlaWdodCA3NDAKL0Rlc2NlbnQgLTE2Ci9JdGFsaWNBbmdsZSAwCi9TdGVtViAxMzEKL01pc3NpbmdXaWR0aCAxMDAwCi9YSGVpZ2h0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OnhtcG1ldGEgeG1sbnM6eD0nYWRvYmU6bnM6bWV0YS8nIHg6eG1wdGs9J1hNUCB0b29sa2l0IDIuOS4xLTEzLCBmcmFtZXdvcmsgMS42Jz4KPHJkZjpSREYgeG1sbnM6cmRmPSdodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjJyB4bWxuczppWD0naHR0cDovL25zLmFkb2JlLmNvbS9pWC8xLjAvJz4KPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9J2U4NTJjMDI0LTA0ZWQtMTFlYy0wMDAwLTUwNzQwMjZjNzE1MicgeG1sbnM6cGRmPSdodHRwOi8vbnMuYWRvYmUuY29tL3BkZi8xLjMvJyBwZGY6UHJvZHVjZXI9J0dQTCBHaG9zdHNjcmlwdCA4LjcwJy8+CjxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PSdlODUyYzAyNC0wNGVkLTExZWMtMDAwMC01MDc0MDI2YzcxNTInIHhtbG5zOnhtcD0naHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyc+PHhtcDpNb2RpZnlEYXRlPjIwMTEtMDgtMjJUMTc6MTM6NTgrMDI6MDA8L3htcDpNb2RpZnlEYXRlPgo8eG1wOkNyZWF0ZURhdGU+MjAxMS0wOC0yMlQxNzoxMzo1OCswMjowMDwveG1wOkNyZWF0ZURhdGU+Cjx4bXA6Q3JlYXRvclRvb2w+UFNjcmlwdDUuZGxsIFZlcnNpb24gNS4yLjI8L3htcDpDcmVhdG9yVG9vbD48L3JkZjpEZXNjcmlwdGlvbj4KPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9J2U4NTJjMDI0LTA0ZWQtMTFlYy0wMDAwLTUwNzQwMjZjNzE1MicgeG1sbnM6eGFwTU09J2h0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8nIHhhcE1NOkRvY3VtZW50SUQ9J2U4NTJjMDI0LTA0ZWQtMTFlYy0wMDAwLTUwNzQwMjZjNzE1MicvPgo8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0nZTg1MmMwMjQtMDRlZC0xMWVjLTAwMDAtNTA3NDAyNmM3MTUyJyB4bWxuczpkYz0naHR0cDovL3B1cmwub3JnL2RjL2VsZW1lbnRzLzEuMS8nIGRjOmZvcm1hdD0nYXBwbGljYXRpb24vcGRmJz48ZGM6dGl0bGU+PHJkZjpBbHQ+PHJkZjpsaSB4bWw6bGFuZz0neC1kZWZhdWx0Jz5NaWNyb3NvZnQgV29yZCAtIFRlc3Rkb2t1bWVudC5kb2M8L3JkZjpsaT48L3JkZjpBbHQ+PC9kYzp0aXRsZT48ZGM6Y3JlYXRvcj48cmRmOlNlcT48cmRmOmxpPmRydTwvcmRmOmxpPjwvcmRmOlNlcT48L2RjOmNyZWF0b3I+PC9yZGY6RGVzY3JpcHRpb24+CjwvcmRmOlJERj4KPC94OnhtcG1ldGE+CiAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIAogICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAKPD94cGFja2V0IGVuZD0ndyc/PgplbmRzdHJlYW0KZW5kb2JqCjIgMCBvYmoKPDwvUHJvZHVjZXIoR1BMIEdob3N0c2NyaXB0IDguNzApCi9DcmVhdGlvbkRhdGUoRDoyMDExMDgyMjE3MTM1OCswMicwMCcpCi9Nb2REYXRlKEQ6MjAxMTA4MjIxNzEzNTgrMDInMDAnKQovVGl0bGUoTWljcm9zb2Z0IFdvcmQgLSBUZXN0ZG9rdW1lbnQuZG9jKQovQ3JlYXRvcihQU2NyaXB0NS5kbGwgVmVyc2lvbiA1LjIuMikKL0F1dGhvcihkcnUpPj5lbmRvYmoKeHJlZgowIDE5CjAwMDAwMDAwMDAgNjU1MzUgZiAKMDAwMDAwMDc3MyAwMDAwMCBuIAowMDAwMDEzMDQ1IDAwMDAwIG4gCjAwMDAwMDA3MTQgMDAwMDAgbiAKMDAwMDAwMDU1NCAwMDAwMCBuIAowMDAwMDAwMDE1IDAwMDAwIG4gCjAwMDAwMDA1MzUgMDAwMDAgbiAKMDAwMDAwMDgzOCAwMDAwMCBuIAowMDAwMDAwOTUwIDAwMDAwIG4gCjAwMDAwMDE2MTUgMDAwMDAgbiAKMDAwMDAwMTMxNCAwMDAwMCBuIAowMDAwMDA3NTEwIDAwMDAwIG4gCjAwMDAwMDA4NzkgMDAwMDAgbiAKMDAwMDAwMDkwOSAwMDAwMCBuIAowMDAwMDAxODMyIDAwMDAwIG4gCjAwMDAwMDc3MzMgMDAwMDAgbiAKMDAwMDAwMTE3MyAwMDAwMCBuIAowMDAwMDAxNTA5IDAwMDAwIG4gCjAwMDAwMTE1NTIgMDAwMDAgbiAKdHJhaWxlcgo8PCAvU2l6ZSAxOSAvUm9vdCAxIDAgUiAvSW5mbyAyIDAgUgovSUQgWzw5QkI1NDVDQ0Q4NUE3OURBNUNDMENFMTdFNEEzRUMwMT48OUJCNTQ1Q0NEODVBNzlEQTVDQzBDRTE3RTRBM0VDMDE+XQo+PgpzdGFydHhyZWYKMTMyNjAKJSVFT0YK';

        $fileName = 'ErgaenzendeUnterlage.pdf';
        $filenamePrefix = 'xbeteiligung';

        // Decode base64 (simulating what SOAP library does)
        $fileContent = base64_decode($base64Content, true);
        static::assertNotFalse($fileContent, 'Base64 decoding failed');
        static::assertNotEmpty($fileContent, 'Decoded content is empty');

        // Test function with decoded content
        $file = $this->sut->saveBinaryFileContent($fileName, $fileContent, $filenamePrefix);

        // Verify file was saved
        $fileId = $file->getId();
        static::assertIsString($fileId);

        $savedFile = $this->sut->get($fileId);
        static::assertSame($fileName, $savedFile->getFileName());
        static::assertSame(strlen($fileContent), $savedFile->getSize());
        static::assertSame('application/pdf', $savedFile->getMimetype());

        // Verify content matches exactly
        $retrievedContent = $this->sut->getContent($savedFile);
        static::assertEquals($fileContent, $retrievedContent);

        // Verify it's a PDF file by checking magic bytes
        // PDF files start with '%PDF'
        static::assertStringStartsWith('%PDF', $retrievedContent, 'PDF file should start with %PDF');
    }
}
