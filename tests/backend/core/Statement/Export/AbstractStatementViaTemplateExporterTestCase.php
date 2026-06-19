<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Base\UnitTestCase;
use ZipArchive;

/**
 * Shared scaffolding for the {@see \demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementViaTemplateExporter}
 * test classes — owns the temp-file bookkeeping, the segment-mock factory, and
 * the DOCX inspection helpers so {@see StatementViaTemplateExporterTest} and
 * {@see StatementViaTemplateExporterIntegrationTest} stay focused on their
 * actual assertions.
 */
abstract class AbstractStatementViaTemplateExporterTestCase extends UnitTestCase
{
    private const EXAMPLE_TEMPLATE_SUBPATH = 'demosplan/DemosPlanCoreBundle/Resources/public/files/statement_template_example_export.docx';

    /**
     * @var list<string>|null
     */
    protected ?array $temporaryFiles = null;

    /**
     * Per-test working copy of the public example DOCX. Tests must never
     * mutate the public source — this temp copy is regenerated for every
     * test and cleaned up in tearDown along with the other temporary files.
     */
    protected ?string $exampleTemplate = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryFiles = [];
        $this->exampleTemplate = $this->reservePath('.docx');
        copy(DemosPlanPath::getRootPath(self::EXAMPLE_TEMPLATE_SUBPATH), $this->exampleTemplate);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles ?? [] as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->temporaryFiles = null;
        $this->exampleTemplate = null;

        parent::tearDown();
    }

    abstract protected function tempFilePrefix(): string;

    protected function makeSegment(string $externId, string $text, string $recommendation): Segment&MockObject
    {
        $segment = $this->createMock(Segment::class);
        $segment->method('getExternId')->willReturn($externId);
        $segment->method('getText')->willReturn($text);
        $segment->method('getRecommendation')->willReturn($recommendation);

        return $segment;
    }

    /**
     * @return list<string>
     */
    protected function getRemainingVariables(string $absolutePath): array
    {
        $templateProcessor = new TemplateProcessor($absolutePath);

        return array_values($templateProcessor->getVariables());
    }

    protected function extractBodyText(string $absolutePath): string
    {
        $zip = new ZipArchive();
        self::assertSame(true, $zip->open($absolutePath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        self::assertNotFalse($xml);
        if (false === preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $matches)) {
            return '';
        }

        return implode("\n", $matches[1]);
    }

    protected function reservePath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), $this->tempFilePrefix()).$extension;
        $this->temporaryFiles ??= [];
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
