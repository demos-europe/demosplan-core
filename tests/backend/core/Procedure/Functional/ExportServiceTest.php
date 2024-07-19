<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableViewMode;
use demosplan\DemosPlanCoreBundle\Logic\News\ServiceOutput as NewsOutput;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ExportService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceOutput as ProcedureOutput;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementListHandlerResult;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\DocxExportResult;
use demosplan\DemosPlanCoreBundle\ValueObject\ToBy;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Psr\Log\LoggerInterface;
use Tests\Base\FunctionalTestCase;
use ZipStream\ZipStream;

class ExportServiceTest extends FunctionalTestCase
{
    /**
     * @var ExportService
     */
    protected $sut;
    private $procedureOutputMock;
    private $zipExportServiceMock;
    private $loggerMock;
    private $newsOutputMock;
    private $assessmentableServiceOutputMock;
    private $assessmentHandlerMock;
    private $docxExportResultMock;
    private $statementServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ExportService::class);
        $this->procedureOutputMock = $this->createMock(ProcedureOutput::class);
        $this->zipExportServiceMock = $this->createMock(ZipExportService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->newsOutputMock = $this->createMock(NewsOutput::class);
        $this->assessmentableServiceOutputMock = $this->createMock(AssessmentTableServiceOutput::class);
        $this->assessmentHandlerMock = $this->createMock(AssessmentHandler::class);
        $this->docxExportResultMock = $this->createMock(DocxExportResult::class);
        $this->statementServiceMock = $this->createMock(StatementService::class);
    }

    public function testGetInstitutionListPhrase(): void
    {
        $phrase = $this->sut->getInstitutionListPhrase();
        self::assertSame('Institution-Liste', $phrase);
    }

    public function testAddTitlePageToZip(){
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $titleForPage = 'titlepage';
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions(['feature_institution_participation']);
        $zip = $this->createMock(ZipStream::class);
        $titlepage = $this->procedureOutputMock
            ->method('generatePdfForTitlePage')
            ->with($procedure->getId(), $titleForPage);
        $this->zipExportServiceMock
            ->method('addStringToZipStream')
            ->with($procedure->getName().'/Deckblatt.pdf', $titlepage, $zip);
        $this->loggerMock
            ->method('info')
            ->with('deckblatt created', ['id' => $procedure->getId(), 'name' => $procedure->getName()]);
        $result = $this->sut->addTitlePageToZip($procedure->getId(), $titleForPage, $zip);
        $this->assertSame($zip, $result);
    }

    public function testAddNewsToZip() {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $procedureId = $procedure->getId();
        $procedureName = $procedure->getName();
        $zip = $this->createMock(ZipStream::class);

        $pdfContent = 'pdf content';
        $newsList = [
            ['pdf' => '/path/to/news1.pdf', 'picture' => '/path/to/picture1.jpg'],
            ['pdf' => '', 'picture' => '/path/to/picture2.jpg'],
            ['pdf' => '/path/to/news3.pdf', 'picture' => ''],
        ];

        $this->newsOutputMock
            ->method('generatePdf')
            ->with($procedureId, 'procedure:'.$procedureId, 'news')
            ->willReturn($pdfContent);

        $this->zipExportServiceMock
            ->method('addStringToZipStream')
            ->with($procedureName.'/Aktuelles.pdf', $pdfContent, $zip);

        $this->newsOutputMock
            ->method('newsListHandler')
            ->with($procedureId, 'procedure:'.$procedureId)
            ->willReturn($newsList);

        $this->zipExportServiceMock
            ->method('addFilePathToZipStream')
            ->with(
                [$newsList[0]['pdf'], $procedureName.'/Anhang/Aktuelles', $zip],
                [$newsList[0]['picture'], $procedureName.'/Anhang/Aktuelles', $zip],
                [$newsList[1]['picture'], $procedureName.'/Anhang/Aktuelles', $zip]
            );

        $result = $this->sut->addNewsToZip($procedureId, $procedureName, $zip);

        $this->assertSame($zip, $result);
    }

    public function testAddAssessmentTableToZip(): void
    {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $procedureId = $procedure->getId();
        $procedureName = $procedure->getName();
        $exportType = 'statementsOnly';
        $expectedFilePath = '/path/to/tmp_export_orig_stn_mock-uuid.docx';
        $zip = $this->createMock(ZipStream::class);

        $rParams = [
            'filters' => [],
            'request' => ['limit' => 1_000_000],
            'items'   => [],
            'sort'    => ToBy::createArray('submitDate', 'desc')
        ];

        $type = [
            'anonymous'  => false,
            'exportType' => $exportType,
            'template'   => 'condensed',
            'sortType'   => 'default',
        ];

        $writer = $this->createMock(WriterInterface::class);
        $exportResult = new DocxExportResult('test-filename.docx', $writer);

        $writer->method('save')->with($this->callback(function($filepath) {
            return strpos($filepath, 'tmp_export_orig_stn_') !== false;
        }));

        $this->assessmentHandlerMock->method('exportDocx')
            ->with($procedureId, $rParams, $type, 'default', false)
            ->willReturn($exportResult);

        $this->zipExportServiceMock->method('addFileToZipStream')
            ->with($expectedFilePath, 'TestProcedure/statements/considerationtable/considerationtable_Liste.docx', $zip);

        $resultZip = $this->sut->addAssessmentTableToZip($procedureId, $procedureName, $exportType, $zip);

        $this->assertSame($zip, $resultZip);
    }

    public function testAddAssessmentTableToZipException(): void
    {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $exportType = 'statementsOnly';
        $zip = $this->createMock(ZipStream::class);

        $this->assessmentHandlerMock->method('exportDocx')->will($this->throwException(new \Exception('Test Exception')));

        $resultZip = $this->sut->addAssessmentTableToZip($procedure->getId(), $procedure->getName(), $exportType, $zip);

        $this->assertSame($zip, $resultZip);
    }

    public function testAddAssessmentTableAnonymousToZipStatementsOnly()
    {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $zip = $this->createMock(ZipStream::class);
        $exportType = 'statementsOnly';
        $writer = $this->createMock(WriterInterface::class);
        $exportResult = new DocxExportResult('test-filename.docx', $writer);

        $writer->method('save')->with($this->callback(function($filepath) {
            return strpos($filepath, 'tmp_export_orig_stn_') !== false;
        }));
        $outputResult = $this->createMock(StatementListHandlerResult::class);

        $outputResult
            ->method('getStatementList')
            ->willReturn([]);

        $this->assessmentHandlerMock
            ->method('exportDocx')
            ->with(
                $procedure->getId(),
                $this->anything(),
                [
                    'anonymous'  => true,
                    'exportType' => $exportType,
                    'template'   => 'condensed',
                    'sortType'   => AssessmentTableServiceOutput::EXPORT_SORT_DEFAULT,
                ],
                AssessmentTableViewMode::DEFAULT_VIEW,
                false
            )
            ->willReturn($exportResult);

        $this->statementServiceMock
            ->method('getStatementsByIds')
            ->willReturn([]);

        // Expect addDocxToZip to be called with correct arguments
        $result = $this->sut->addAssessmentTableAnonymousToZip(
            $procedure->getId(),
            $procedure->getName(),
            $exportType,
            $zip
        );

        $this->assertSame($zip, $result);
    }

    public function testAddMapToZip()
    {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $zip = $this->createMock(ZipStream::class);
        $procedureAsArray = [
            'settings' => [
                'planDrawPDF' => 'path/to/planDrawPDF',
                'planPDF' => 'path/to/planPDF',
            ]
        ];

        $this->procedureOutputMock
            ->method('getProcedureWithPhaseNames')
            ->with($procedure->getId())
            ->willReturn($procedureAsArray);

        $this->zipExportServiceMock
            ->method('addFilePathToZipStream')
            ->with(
                ['path/to/planDrawPDF', $procedure->getName().'/Planzeichnung', $zip]
            );
        $this->zipExportServiceMock
            ->method('addFilePathToZipStream')
            ->with(
                ['path/to/planPDF', $procedure->getName().'/Planzeichnung', $zip]
            );

        $this->loggerMock
            ->method('info')
            ->with('planning_documents created', ['id' => $procedure->getId(), 'name' => $procedure->getName()]);

        $result = $this->sut->addMapToZip($procedure->getId(), $procedure->getName(), $zip);

        $this->assertSame($zip, $result);
    }

}
