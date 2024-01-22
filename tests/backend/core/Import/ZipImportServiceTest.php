<?php

namespace Tests\Core\Import;

use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\ZipImportService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Base\FunctionalTestCase;
use ValueError;
use Zenstruck\Foundry\Proxy;

class ZipImportServiceTest extends FunctionalTestCase
{
    private ?Finder $finder;
    private ?FileServiceInterface $fileService;

    /** @var ZipImportService */
    protected $sut;
    private null|Procedure|Proxy $testProcedure;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ZipImportService::class);
        $this->testProcedure = ProcedureFactory::createOne();
        $this->fileService = $this->getContainer()->get(FileServiceInterface::class);
        $this->finder = Finder::create();
    }

    private function getFile($filename): ?FileInfo {
        $currentDirectoryPath = DemosPlanPath::getTestPath('backend/core/Import');
        $this->finder->files()->in($currentDirectoryPath)->name($filename);

        if ($this->finder->hasResults()) {
            /** @var SplFileInfo $file */
            foreach ($this->finder as $file) {
                if ($filename === $file->getFilename()) {

//                    echo var_dump($file->getFilename());

                    $fileInfo = new FileInfo(
                        $this->fileService->createHash(),
                        $file->getFilename(),
                        $file->getSize(),
                        'application/zip',
                        $file->getPath(),
                        $file->getRealPath(),
                        $this->testProcedure->object()
                    );
                    return $fileInfo;
                }
            }
        }

        return null;
    }

    public function testCreateFileMapFromZip(): void
    {
        $splFileInfo = new SplFileInfo(
            $this->getFile("Abwaegungstabelle_Export_Testfile.zip")->getAbsolutePath(),
            '',
            $this->getFile("Abwaegungstabelle_Export_Testfile.zip")->getHash()
        );

        $resultArray = $this->sut->createFileMapFromZip($splFileInfo, $this->testProcedure->getId());

        self::assertCount(6, $resultArray);
        self::arrayHasKey('953c76bfb58346089b8e432becf6c334');
        self::arrayHasKey('d76a37894e17304f2955b24a3689ab68');
        self::arrayHasKey('abd6bcf0d057a37b39efeb8b9e38cb85');
        self::arrayHasKey('Abwägungstabelle-24-11-2023-08');
        self::arrayHasKey('e63f309f5abf0d9bd667245fcdceb9bf');
        self::arrayHasKey('e92462e3be16c8ed8131c1fc7fc95a94');

        self::assertInstanceOf(File::class, $resultArray['953c76bfb58346089b8e432becf6c334']);
        self::assertInstanceOf(File::class, $resultArray['d76a37894e17304f2955b24a3689ab68']);
        self::assertInstanceOf(File::class, $resultArray['abd6bcf0d057a37b39efeb8b9e38cb85']);
        self::assertInstanceOf(File::class, $resultArray['e63f309f5abf0d9bd667245fcdceb9bf']);
        self::assertInstanceOf(File::class, $resultArray['e92462e3be16c8ed8131c1fc7fc95a94']);
        self::assertInstanceOf(SplFileInfo::class, $resultArray['Abwägungstabelle-24-11-2023-08']);
    }

    //This test takes the Abwaegungstabelle_Export_Error_Testfile.zip which contains an error.txt file, causing an InvalidArgumentException
    //and consequently a Demos Exception
    public function testDemosExceptionWithErrorFileOnCreateFileMapFromZip(): void
    {
        $this->expectException(DemosException::class);

        $splFileInfo = new SplFileInfo(
            $this->getFile("Abwaegungstabelle_Export_Error_Testfile.zip")->getAbsolutePath(),
            '',
            $this->getFile("Abwaegungstabelle_Export_Error_Testfile.zip")->getHash()
        );

        $resultArray = $this->sut->createFileMapFromZip($splFileInfo, $this->testProcedure->getId());
    }

    public function testExceptionOnCreateFileMapFromZip(): void
    {
        $this->expectException(DemosException::class);

        $splFileInfo = new SplFileInfo(
            '../../../../../../../..',
            '',
            $this->getFile("Abwaegungstabelle_Export_Testfile.zip")->getHash()
        );
        $resultArray = $this->sut->createFileMapFromZip($splFileInfo, $this->testProcedure->getId());
    }

    public function testExtractZipToTempFolder(): void
    {
        $splFileInfo = new SplFileInfo(
            $this->getFile("Abwaegungstabelle_Export_Testfile.zip")->getAbsolutePath(),
            '',
            $this->getFile("Abwaegungstabelle_Export_Testfile.zip")->getHash()
        );

        $result = $this->sut->extractZipToTempFolder($splFileInfo, $this->testProcedure->getId());

        self::assertEquals(
            '/tmp/'.$this->currentUserService->getUser()->getId().'/'.$this->testProcedure->getId().
            '/Abwaegungstabelle_Export_Testfile.zip/Auswertung_Abwaegungstabelle_Export',//todo mimetype missing?
            $result
        );
    }

    public function testExceptionOnExtractZipToTempFolder(): void
    {
        $this->expectException(ValueError::class);

        $splFileInfo = new SplFileInfo(
            '../../../../../../../..',
            '',
            $this->getFile("Abwaegungstabelle_Export_Testfile.zip")->getHash()
        );

        $result = $this->sut->extractZipToTempFolder($splFileInfo, $this->testProcedure->getId());
    }

    public function testGetStatementAttachmentImportDir(): void
    {
        $user = $this->currentUserService->getUser();

        $result = $this->sut->getStatementAttachmentImportDir(
            $this->testProcedure->getId(),
            $this->getFile("Abwaegungstabelle_Export_Testfile.zip")->getFileName(),
            $user
        );

        self::assertEquals(
            '/tmp/'.$user->getId().'/'.$this->testProcedure->getId().'/Abwaegungstabelle_Export_Testfile.zip',
            $result
        );
    }

    public function testExceptionOnGetStatementAttachmentImportDir(): void
    {
        $this->expectException(InvalidDataException::class);

        $this->sut->getStatementAttachmentImportDir(
            $this->testProcedure->getId(),
            '../../../../../../../..',
            $this->loginTestUser()
        );
    }
}
