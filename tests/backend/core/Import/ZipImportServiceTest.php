<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Import;

use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\ZipImportService;
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
    private Procedure|Proxy|null $testProcedure;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ZipImportService::class);
        $this->testProcedure = ProcedureFactory::createOne();
    }

    public function testCreateFileMapFromZip(): void
    {
        // local file usage is ok here
        $splFileInfo = new SplFileInfo(
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Testfile.zip', 'application/zip', $this->testProcedure->object())->getAbsolutePath(),
            '',
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Testfile.zip', 'application/zip', $this->testProcedure->object())->getHash()
        );

        $resultArray = $this->sut->createFileMapFromZip($splFileInfo, $this->testProcedure->getId());

        self::assertCount(6, $resultArray);
        self::arrayHasKey('953c76bfb58346089b8e432becf6c334');
        self::arrayHasKey('d76a37894e17304f2955b24a3689ab68');
        self::arrayHasKey('abd6bcf0d057a37b39efeb8b9e38cb85');
        self::arrayHasKey('Abwagungstabelle-24-11-2023-08_14');
        self::arrayHasKey('e63f309f5abf0d9bd667245fcdceb9bf');
        self::arrayHasKey('e92462e3be16c8ed8131c1fc7fc95a94');

        self::assertInstanceOf(File::class, $resultArray['953c76bfb58346089b8e432becf6c334']);
        self::assertInstanceOf(File::class, $resultArray['d76a37894e17304f2955b24a3689ab68']);
        self::assertInstanceOf(File::class, $resultArray['abd6bcf0d057a37b39efeb8b9e38cb85']);
        self::assertInstanceOf(File::class, $resultArray['e63f309f5abf0d9bd667245fcdceb9bf']);
        self::assertInstanceOf(File::class, $resultArray['e92462e3be16c8ed8131c1fc7fc95a94']);
        self::assertInstanceOf(SplFileInfo::class, $resultArray['Abwagungstabelle-24-11-2023-08']);
    }

    // This test takes the Abwaegungstabelle_Export_Error_Testfile.zip which contains an error.txt file, causing an InvalidArgumentException
    // and consequently a Demos Exception
    public function testDemosExceptionWithErrorFileOnCreateFileMapFromZip(): void
    {
        $this->expectException(DemosException::class);

        $splFileInfo = new SplFileInfo(
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Error_Testfile.zip', 'application/zip', $this->testProcedure->object())->getAbsolutePath(),
            '',
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Error_Testfile.zip', 'application/zip', $this->testProcedure->object())->getHash()
        );

        $resultArray = $this->sut->createFileMapFromZip($splFileInfo, $this->testProcedure->getId());
    }

    public function testExceptionOnCreateFileMapFromZip(): void
    {
        $this->expectException(DemosException::class);

        $splFileInfo = new SplFileInfo(
            '../../../../../../../..',
            '',
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Testfile.zip', 'application/zip', $this->testProcedure->object())->getHash()
        );
        $resultArray = $this->sut->createFileMapFromZip($splFileInfo, $this->testProcedure->getId());
    }

    public function testExtractZipToTempFolder(): void
    {
        $splFileInfo = new SplFileInfo(
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Testfile.zip', 'application/zip', $this->testProcedure->object())->getAbsolutePath(),
            '',
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Testfile.zip', 'application/zip', $this->testProcedure->object())->getHash()
        );

        $result = $this->sut->extractZipToTempFolder($splFileInfo, $this->testProcedure->getId());

        self::assertStringEndsWith(
            $this->currentUserService->getUser()->getId().'/'.$this->testProcedure->getId().
            '/Abwaegungstabelle_Export_Testfile.zip/Auswertung_Abwaegungstabelle_Export',// todo mimetype missing?
            $result
        );
    }

    public function testExceptionOnExtractZipToTempFolder(): void
    {
        $this->expectException(ValueError::class);

        $splFileInfo = new SplFileInfo(
            '../../../../../../../..',
            '',
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Testfile.zip', 'application/zip', $this->testProcedure->object())->getHash()
        );

        $result = $this->sut->extractZipToTempFolder($splFileInfo, $this->testProcedure->getId());
    }

    public function testGetStatementAttachmentImportDir(): void
    {
        $user = $this->currentUserService->getUser();

        $result = $this->sut->getStatementAttachmentImportDir(
            $this->testProcedure->getId(),
            $this->getFile('backend/core/Import', 'Abwaegungstabelle_Export_Testfile.zip', 'application/zip', $this->testProcedure->object())->getFileName(),
            $user
        );

        self::assertStringEndsWith(
            $user->getId().'/'.$this->testProcedure->getId().'/Abwaegungstabelle_Export_Testfile.zip',
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
