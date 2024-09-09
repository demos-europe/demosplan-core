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

        $this->sut = self::$container->get(FileService::class);
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
        $globalConfig = self::$container->get(GlobalConfigInterface::class);
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
}
