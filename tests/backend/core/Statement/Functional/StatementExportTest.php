<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadFileData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadStatementData;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FormOptionsResolver;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\SimpleSpreadsheetService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTablePdfExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableZipExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;
use Twig\Environment;

class StatementExportTest extends FunctionalTestCase
{
    /**
     * @var AssessmentTableZipExporter;
     */
    protected $sut;

    private $statement;

    public function setUp(): void
    {
        parent::setUp();
        $assessmentHandler = $this->getContainer()->get(AssessmentHandler::class);
        $assessmentTableServiceOutput = $this->getContainer()->get(AssessmentTableServiceOutput::class);
        $editorService = $this->getContainer()->get(EditorService::class);
        $environment = $this->getContainer()->get(Environment::class);
        $formOptionsResolver = $this->getContainer()->get(FormOptionsResolver::class);
        $loggerInterface = $this->getContainer()->get(LoggerInterface::class);
        $permissionsInterface = $this->getContainer()->get(PermissionsInterface::class);
        $serviceImporter = $this->getContainer()->get(ServiceImporter::class);
        $simpleSpreadsheetService = $this->getContainer()->get(SimpleSpreadsheetService::class);
        $statementHandler = $this->getContainer()->get(StatementHandler::class);
        $translatorInterface = $this->getContainer()->get(TranslatorInterface::class);
        $statementService = $this->getContainer()->get(StatementService::class);
        $assessmentTablePdfExporter = $this->getContainer()->get(AssessmentTablePdfExporter::class);
        $fileService = $this->getContainer()->get(FileService::class);
        $requestStack = $this->createMock(RequestStack::class);
        $sessionInterfaceMock = $this->createMock(SessionInterface::class);
        $requestStack->method('getSession')->willReturn($sessionInterfaceMock);
        $this->statement = $this->getStatementReference(LoadStatementData::TEST_STATEMENT);
        $file = $this->getFileReference(LoadFileData::PDF_TEST_FILE);
        $statementAttachment = new StatementAttachment();
        $statementAttachment->setFile($file);
        $statementAttachment->setType(StatementAttachmentInterface::SOURCE_STATEMENT);
        $statementAttachment->setStatement($this->statement);
        $this->getEntityManager()->persist($statementAttachment);
        $this->statement->addAttachment($statementAttachment);
        $this->getEntityManager()->flush();
        $currentProcedureService = $this->createMock(CurrentProcedureService::class);
        $currentProcedureService->method('getProcedure')->willReturn($this->statement->getProcedure());
        $this->sut = new AssessmentTableZipExporter(
            $assessmentHandler,
            $assessmentTableServiceOutput,
            $currentProcedureService,
            $editorService,
            $environment,
            $formOptionsResolver,
            $loggerInterface,
            $permissionsInterface,
            $requestStack,
            $serviceImporter,
            $simpleSpreadsheetService,
            $statementHandler,
            $translatorInterface,
            $assessmentTablePdfExporter,
            $statementService,
            $fileService
        );
    }

    public function testInvokeAssessmentTableZipExporter()
    {
        $this->loginTestUser();
        $parameters = [
            'procedureId' => $this->statement->getProcedure()->getId(),
            'original'    => $this->statement->getOriginal(),
            'anonymous'   => false,
            'exportType'  => 'statementsWithAttachments',
        ];
        $sut = $this->sut;
        $return = $sut($parameters);

        self::assertCount(0, $return['attachments']);
        self::assertInstanceOf(Xlsx::class, $return['xlsx']['writer']);
    }
}
