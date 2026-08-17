<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Unit;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\SimpleSpreadsheetService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Formatter\StatementFormatter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The reference column carries the statement id so an instance importing the export can pair its
 * statements with the ones they originate from.
 */
class AssessmentTableXlsExporterSourceReferenceTest extends TestCase
{
    protected ?AssessmentTableXlsExporter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        // No permission is enabled, so only the ungated columns are part of the format.
        $permissions = $this->createMock(PermissionsInterface::class);
        $permissions->method('hasPermission')->willReturn(false);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->sut = new AssessmentTableXlsExporter(
            $this->createMock(AssessmentHandler::class),
            $this->createMock(AssessmentTableServiceOutput::class),
            $this->createMock(CurrentProcedureService::class),
            $this->createMock(CurrentUserInterface::class),
            $this->createMock(DocumentWriterSelector::class),
            $this->createMock(EditorService::class),
            $this->createMock(Environment::class),
            $this->createMock(LoggerInterface::class),
            $permissions,
            $this->createMock(RequestStack::class),
            $this->createMock(ServiceImporter::class),
            $this->createMock(SimpleSpreadsheetService::class),
            $this->createMock(StatementHandler::class),
            $this->createMock(StatementFormatter::class),
            $translator
        );
    }

    public function testStatementFormatCarriesTheStatementId(): void
    {
        $columnKeys = array_column($this->sut->selectFormat('statements'), 'key');

        self::assertContains('id', $columnKeys);
    }

    public function testSegmentFormatCarriesTheStatementId(): void
    {
        $columnKeys = array_column($this->sut->selectFormat('segments'), 'key');

        self::assertContains('id', $columnKeys);
    }

    public function testStatementAttachmentsFormatCarriesTheStatementId(): void
    {
        $columnKeys = array_column($this->sut->selectFormat('statementsWithAttachments'), 'key');

        self::assertContains('id', $columnKeys);
    }
}
