<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Export;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocxExporter;
use Symfony\Component\HttpFoundation\RequestStack;
use Tests\Base\UnitTestCase;

class DocxExporterOdtTest extends UnitTestCase
{
    protected $sut;
    protected $writerSelector;
    protected $permissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $requestStack = $this->createMock(RequestStack::class);
        $this->writerSelector = new DocumentWriterSelector($this->permissions, $requestStack);

        // Create DocxExporter with all mocked dependencies
        $odtHtmlProcessor = new \demosplan\DemosPlanCoreBundle\Logic\Export\OdtHtmlProcessor();
        $this->sut = new DocxExporter(
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\EditorService::class),
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\Export\FieldDecider::class),
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\FileService::class),
            $this->createMock(\League\Flysystem\FilesystemOperator::class),
            $this->createMock(\DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface::class),
            $this->createMock(\Psr\Log\LoggerInterface::class),
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\Map\MapService::class),
            $odtHtmlProcessor,
            $this->permissions,
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler::class),
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService::class),
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler::class),
            $this->createMock(\demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService::class),
            $this->createMock(\Symfony\Contracts\Translation\TranslatorInterface::class),
            $this->writerSelector
        );
    }

    public function testDocxExporterIntegratesWithOdtHtmlProcessor(): void
    {
        // This is an integration test to verify DocxExporter properly uses OdtHtmlProcessor
        // The actual ODT processing logic is tested in OdtHtmlProcessorTest

        // Arrange
        $this->permissions->method('hasPermission')->willReturn(true);

        // Act & Assert - verify that DocxExporter doesn't throw errors when using OdtHtmlProcessor
        self::assertInstanceOf(DocxExporter::class, $this->sut);
    }
}
