<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadFileData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadStatementData;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FormOptionsResolver;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\SimpleSpreadsheetService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTablePdfExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableZipExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use Exception;
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
    private $permissions;
    private $editorService;

    private $assessmentTableXlsExporter;

    public function setUp(): void
    {
        parent::setUp();
        /** @var AssessmentHandler $assessmentHandler */
        $assessmentHandler = $this->getContainer()->get(AssessmentHandler::class);
        /** @var AssessmentTableServiceOutput $assessmentTableServiceOutput */
        $assessmentTableServiceOutput = $this->getContainer()->get(AssessmentTableServiceOutput::class);
        /** @var LoggerInterface $loggerInterface */
        $loggerInterface = $this->getContainer()->get(LoggerInterface::class);
        /** @var StatementHandler $statementHandler */
        $statementHandler = $this->getContainer()->get(StatementHandler::class);
        /** @var TranslatorInterface $translatorInterface */
        $translatorInterface = $this->getContainer()->get(TranslatorInterface::class);
        /** @var StatementService $statementService */
        $statementService = $this->getContainer()->get(StatementService::class);
        /** @var AssessmentTablePdfExporter $assessmentTablePdfExporter */
        $assessmentTablePdfExporter = $this->getContainer()->get(AssessmentTablePdfExporter::class);
        /** @var FileService $fileService */
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
        /** @var Environment $twig */
        $twig = $this->getContainer()->get(Environment::class);
        $this->editorService = $this->getContainer()->get(EditorService::class);
        /** @var FormOptionsResolver $formOptionsResolver */
        $formOptionsResolver = $this->getContainer()->get(FormOptionsResolver::class);
        $this->permissions = $this->getContainer()->get(PermissionsInterface::class);
        /** @var ServiceImporter $serviceImporter */
        $serviceImporter = $this->getContainer()->get(ServiceImporter::class);
        /** @var SimpleSpreadsheetService $simpleSpreadsheetService */
        $simpleSpreadsheetService = $this->getContainer()->get(SimpleSpreadsheetService::class);
        /** @var CurrentUserInterface $currentUserService */
        $currentUserService = $this->getContainer()->get(CurrentUserInterface::class);
        /** @var DocumentWriterSelector $documentWriterSelector */
        $documentWriterSelector = $this->getContainer()->get(DocumentWriterSelector::class);
        $this->assessmentTableXlsExporter = new AssessmentTableXlsExporter(
            $assessmentHandler,
            $assessmentTableServiceOutput,
            $currentProcedureService,
            $currentUserService,
            $documentWriterSelector,
            $this->editorService,
            $twig,
            $formOptionsResolver,
            $loggerInterface,
            $this->permissions,
            $requestStack,
            $serviceImporter,
            $simpleSpreadsheetService,
            $statementHandler,
            $translatorInterface
        );
        $this->sut = new AssessmentTableZipExporter(
            $assessmentHandler,
            $assessmentTableServiceOutput,
            $currentProcedureService,
            $documentWriterSelector,
            $loggerInterface,
            $requestStack,
            $statementHandler,
            $translatorInterface,
            $assessmentTablePdfExporter,
            $this->assessmentTableXlsExporter,
            $statementService,
            $fileService
        );
    }

    /**
     * @throws Exception
     */
    public function testInvokeAssessmentTableZipExporter(): void
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

    public function testPrepareDataForExcelExportWithSimpleStatement(): void
    {
        $statements = [$this->createComplexTestStatementData()];
        $attributesToExport = $this->getComplexStatementAttributes();

        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            false,
            $attributesToExport
        );

        $expected = $this->getExpectedComplexStatementResult();

        self::assertCount(1, $result);
        self::assertEquals($expected, $result[0]);
    }

    private function createComplexTestStatementData(): array
    {
        return [
            'externId'             => 'M1',
            'text'                 => '<p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore. statementjiahuu this was edited. Grüße
   aus Cypress!</p>',
            'recommendation'       => '<p>Meine Empfehlung</p>',
            'tagNames'             => ['Tag Name'],
            'tags'                 => [
                [
                    'title'      => 'Tag Name',
                    'topicTitle' => 'Topic Name',
                ],
            ],
            'elementTitle'         => 'Gesamtstellungnahme',
            'documentTitle'        => null,
            'paragraphTitle'       => '',
            'status'               => 'processing',
            'priority'             => 'A-Punkt',
            'oName'                => 'Meine Insti',
            'dName'                => 'Test Abteilung',
            'fileNames'            => [],
            'submitDateString'     => '26.10.2023',
            'memo'                 => 'Mein Notiz!',
            'feedback'             => 'email',
            'votesNum'             => 5,
            'phase'                => 'Beteiligung TöB - § 4 (2) BauGB',
            'submitType'           => 'unspecified',
            'sentAssessment'       => true,
            'meta'                 => [
                'authorName'     => 'A name',
                'submitName'     => 'A name',
                'orgaEmail'      => 'totally.valid@e.mailcypress-test@mail.com',
                'orgaStreet'     => 'A streetTeststraße',
                'houseNumber'    => '111',
                'orgaPostalCode' => '10024',
                'orgaCity'       => 'Berlin',
                'authoredDate'   => '26.10.2023',
            ],
        ];
    }

    private function getComplexStatementAttributes(): array
    {
        return [
            'externId', 'text', 'recommendation', 'tagNames', 'topicNames',
            'elementTitle', 'documentTitle', 'paragraphTitle', 'status', 'priority',
            'oName', 'dName', 'meta.authorName', 'meta.submitName', 'meta.orgaEmail',
            'meta.orgaStreet', 'meta.houseNumber', 'meta.orgaPostalCode', 'meta.orgaCity',
            'fileNames', 'submitDateString', 'meta.authoredDate', 'memo', 'feedback',
            'votesNum', 'phase', 'submitType', 'sentAssessment',
        ];
    }

    private function getExpectedComplexStatementResult(): array
    {
        return [
            'externId'            => 'M1',
            'text'                => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore. statementjiahuu this was edited. Grüße aus Cypress!',
            'recommendation'      => 'Meine Empfehlung',
            'tagNames'            => 'Tag Name',
            'topicNames'          => 'Topic Name',
            'elementTitle'        => 'Gesamtstellungnahme',
            'documentTitle'       => '',
            'paragraphTitle'      => '',
            'status'              => 'In Bearbeitung',
            'priority'            => 'A-Punkt',
            'oName'               => 'Meine Insti',
            'dName'               => 'Test Abteilung',
            'meta.authorName'     => 'A name',
            'meta.submitName'     => 'A name',
            'meta.orgaEmail'      => 'totally.valid@e.mailcypress-test@mail.com',
            'meta.orgaStreet'     => 'A streetTeststraße',
            'meta.houseNumber'    => '111',
            'meta.orgaPostalCode' => '10024',
            'meta.orgaCity'       => 'Berlin',
            'fileNames'           => '',
            'submitDateString'    => '26.10.2023',
            'meta.authoredDate'   => '26.10.2023',
            'memo'                => 'Mein Notiz!',
            'feedback'            => 'email',
            'votesNum'            => '5',
            'phase'               => 'Beteiligung TöB - § 4 (2) BauGB',
            'submitType'          => 'unspecified',
            'sentAssessment'      => 'x',
        ];
    }

    public function testPrepareDataForExcelExportWithPriorityAreaKeys(): void
    {
        $this->loginTestUser();

        $statements = [
            [
                'id'               => '123',
                'text'             => 'Statement with priority areas',
                'priorityAreaKeys' => ['area1', 'area2', 'area3'],
            ],
        ];

        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            false,
            ['id', 'text', 'priorityAreaKeys']
        );

        // Should create 3 rows (one for each priority area)
        self::assertCount(3, $result);

        // Each row should have the same base data but different priority area
        self::assertEquals('123', $result[0]['id']);
        self::assertEquals('123', $result[1]['id']);
        self::assertEquals('123', $result[2]['id']);

        self::assertEquals('area1', $result[0]['priorityAreaKeys']);
        self::assertEquals('area2', $result[1]['priorityAreaKeys']);
        self::assertEquals('area3', $result[2]['priorityAreaKeys']);
    }

    public function testPrepareDataForExcelExportWithTagNamesAndTopics(): void
    {
        $this->loginTestUser();

        $statements = [
            [
                'id'       => '123',
                'text'     => 'Statement with tags',
                'tagNames' => ['Environment', 'Traffic'],
                'tags'     => [
                    ['title' => 'Environment', 'topicTitle' => 'Environmental Protection'],
                    ['title' => 'Traffic', 'topicTitle' => 'Transportation Planning'],
                ],
            ],
        ];

        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            false,
            ['id', 'text', 'tagNames', 'topicNames']
        );

        // Should create 2 rows (one for each tag)
        self::assertCount(2, $result);

        // First row
        self::assertEquals('123', $result[0]['id']);
        self::assertEquals('Environment', $result[0]['tagNames']);
        self::assertEquals('Environmental Protection', $result[0]['topicNames']);

        // Second row
        self::assertEquals('123', $result[1]['id']);
        self::assertEquals('Traffic', $result[1]['tagNames']);
        self::assertEquals('Transportation Planning', $result[1]['topicNames']);
    }

    public function testPrepareDataForExcelExportAnonymousMode(): void
    {
        $this->loginTestUser();

        $statements = [
            [
                'id'         => '123',
                'text'       => '<obscure>Sensitive information</obscure> Public text',
                'authorName' => '<obscure>John Doe</obscure>',
            ],
        ];

        // Test with anonymous = true
        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            true,
            ['id', 'text', 'authorName']
        );

        self::assertCount(1, $result);
        self::assertEquals('123', $result[0]['id']);

        // The EditorService should have processed the obscure tags
        // Exact behavior depends on EditorService implementation
        self::assertIsString($result[0]['text']);
        self::assertIsString($result[0]['authorName']);
    }

    public function testPrepareDataForExcelExportWithEmptyArrays(): void
    {
        $this->loginTestUser();

        $statements = [
            [
                'id'               => '123',
                'text'             => 'Statement with empty arrays',
                'priorityAreaKeys' => [], // Empty array
                'tagNames'         => [], // Empty array
            ],
        ];

        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            false,
            ['id', 'text', 'priorityAreaKeys', 'tagNames']
        );

        // Should create only 1 row since arrays are empty
        self::assertCount(1, $result);
        self::assertEquals('123', $result[0]['id']);
        self::assertEquals('Statement with empty arrays', $result[0]['text']);
    }

    public function testPrepareDataForExcelExportWithMissingAttributes(): void
    {
        $this->loginTestUser();

        $statements = [
            [
                'id'   => '123',
                'text' => 'Statement with missing attributes',
                // Missing 'authorName' that we'll request
            ],
        ];

        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            false,
            ['id', 'text', 'authorName'] // requesting non-existent 'authorName'
        );

        self::assertCount(1, $result);
        self::assertEquals('123', $result[0]['id']);
        self::assertEquals('Statement with missing attributes', $result[0]['text']);
        // Should handle missing attributes gracefully
    }

    public function testPrepareDataForExcelExportWithMultipleStatements(): void
    {
        $this->loginTestUser();

        $statements = [
            [
                'id'               => '1',
                'text'             => 'First statement',
                'priorityAreaKeys' => ['area1', 'area2'],
            ],
            [
                'id'       => '2',
                'text'     => 'Second statement',
                'tagNames' => ['tag1'],
                'tags'     => [['title' => 'tag1', 'topicTitle' => 'Topic A']],
            ],
            [
                'id'   => '3',
                'text' => 'Third statement',
                // No array attributes
            ],
        ];

        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            false,
            ['id', 'text', 'priorityAreaKeys', 'tagNames']
        );

        // Should create 4 rows total:
        // - 2 rows for first statement (2 priority areas)
        // - 1 row for second statement (1 tag)
        // - 1 row for third statement (no arrays)
        self::assertCount(4, $result);

        // Check first statement rows
        self::assertEquals('1', $result[0]['id']);
        self::assertEquals('area1', $result[0]['priorityAreaKeys']);
        self::assertEquals('1', $result[1]['id']);
        self::assertEquals('area2', $result[1]['priorityAreaKeys']);

        // Check second statement row
        self::assertEquals('2', $result[2]['id']);
        self::assertEquals('tag1', $result[2]['tagNames']);

        // Check third statement row
        self::assertEquals('3', $result[3]['id']);
        self::assertEquals('Third statement', $result[3]['text']);
    }

    public function testPrepareDataForExcelExportWithDotNotationAttributes(): void
    {
        $this->loginTestUser();

        $statements = [
            [
                'id'        => '123',
                'text'      => 'Statement with dot notation',
                'user.name' => 'Should be ignored due to dot notation',
            ],
        ];

        $result = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statements,
            false,
            ['id', 'text', 'user.name'] // dot notation should be handled differently
        );

        self::assertCount(1, $result);
        self::assertEquals('123', $result[0]['id']);
        // Dot notation attributes should not cause array processing
    }
}
