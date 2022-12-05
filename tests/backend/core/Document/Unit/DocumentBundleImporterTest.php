<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Unit;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\RouterInterface;
use Tests\Base\FunctionalTestCase;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
use demosplan\DemosPlanDocumentBundle\Exception\ServiceImporterException;
use demosplan\DemosPlanDocumentBundle\Logic\ParagraphService;
use demosplan\DemosPlanDocumentBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanDocumentBundle\Tools\ServiceImporter;

/**
 * Class DocumentBundleImporterTest.
 *
 * @group UnitTest
 */
class DocumentBundleImporterTest extends FunctionalTestCase
{
    /**
     * @var ServiceImporter
     */
    protected $sut;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->sut = new ServiceImporter(
            self::$container->get(FileService::class),
            self::$container->get(GlobalConfigInterface::class),
            self::$container->get(LoggerInterface::class),
            self::$container->get(MessageBagInterface::class),
            self::$container->get(ParagraphRepository::class),
            self::$container->get(ParagraphService::class),
            self::$container->get(RouterInterface::class)
        );
    }

    public function testAllowedFileUploadFail()
    {
        $this->expectException(FileException::class);

        $file = new UploadedFile(__DIR__.'/res/db_2Ebenen_wenigeDateien.zip', 'db_2Ebenen_wenigeDateien.zip');
        $this->sut->checkFileIsValidToImport($file, []);
    }

    public function testParagraphImportCreateParagraphs()
    {
        $procedureId = $this->fixtures->getReference('testProcedure')->getId();
        $elementId = $this->fixtures->getReference('testElement1')->getId();

        $paragraphs = [
            [
                'text'         => 'Paragraph text',
                'title'        => '1. Überschrift 1',
                'files'        => null,
                'nestingLevel' => 0,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.1. Überschrift 1.1',
                'files'        => null,
                'nestingLevel' => 1,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.2. Überschrift 1.2',
                'files'        => null,
                'nestingLevel' => 1,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.2.1. Überschrift 1.2.1',
                'files'        => null,
                'nestingLevel' => 2,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '2. Überschrift 1',
                'files'        => null,
                'nestingLevel' => 0,
            ],
        ];

        $importResult = [
            'procedure'  => $procedureId,
            'elementId'  => $elementId,
            'category'   => 'paragraph',
            'paragraphs' => $paragraphs,
        ];
        $paragraphService = self::$container->get(ParagraphService::class);

        $procedureParagraphsBefore = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(3, $procedureParagraphsBefore);

        $this->sut->createParagraphsFromImportResult($importResult, $procedureId);

        $procedureParagraphsAfter = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(8, $procedureParagraphsAfter);
        static::assertNull($procedureParagraphsAfter[3]['parent']);
        static::assertNotNull($procedureParagraphsAfter[3]['children']);
        static::assertCount(2, $procedureParagraphsAfter[3]['children']);
        static::assertNotNull($procedureParagraphsAfter[4]['parent']);
        static::assertNotNull($procedureParagraphsAfter[4]['children']);
        static::assertNotNull($procedureParagraphsAfter[5]['parent']);
        static::assertNotNull($procedureParagraphsAfter[5]['children']);
        static::assertCount(1, $procedureParagraphsAfter[5]['children']);
        static::assertNotNull($procedureParagraphsAfter[6]['parent']);
        static::assertNotNull($procedureParagraphsAfter[6]['children']);
        static::assertNull($procedureParagraphsAfter[7]['parent']);
        static::assertNotNull($procedureParagraphsAfter[7]['children']);
        static::assertCount(0, $procedureParagraphsAfter[7]['children']);
    }

    /**
     * @dataProvider getCreateParagraphExceptionValues
     *
     * @param array $importResult
     */
    public function testParagraphImportCreateParagraphsExceptions($importResult)
    {
        $this->expectException(InvalidArgumentException::class);

        $procedureId = $this->fixtures->getReference('testProcedure')->getId();
        $this->sut->createParagraphsFromImportResult($importResult, $procedureId);
        $this->fail('Exception should be raised');
    }

    public function getCreateParagraphExceptionValues(): array
    {
        return [
            [[
                'elementId'  => 'sdf',
                'category'   => 'paragraph',
                'paragraphs' => [],
            ]],
            [[
                'procedure'  => 'sdf',
                'category'   => 'paragraph',
                'paragraphs' => [],
            ]],
            [[
                'procedure'  => 'asdasd',
                'elementId'  => 'sdf',
                'paragraphs' => [],
            ]],
            [[
                'procedure' => 'sdfsdf',
                'elementId' => 'sdf',
                'category'  => 'paragraph',
            ]],
            [[
                'elementId' => 'sdf',
                'category'  => 'paragraph',
            ]],
            [[
            ]],
        ];
    }

    public function testParagraphImportCreateParagraphsWithInvalidParagraphs()
    {
        $procedureId = $this->fixtures->getReference('testProcedure')->getId();
        $elementId = $this->fixtures->getReference('testElement1')->getId();

        $paragraphs = [
            [
                'text'         => 'Paragraph text',
                'title'        => '1. Überschrift 1',
                'files'        => null,
                'nestingLevel' => 0,
            ],
            [
                'text'         => 'Paragraph text',
                'files'        => null,
                'nestingLevel' => 1,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.2. Überschrift 1.2',
                'files'        => null,
                'nestingLevel' => 1,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.2.1. Überschrift 1.2.1',
                'files'        => null,
                'nestingLevel' => 2,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '2. Überschrift 1',
                'nestingLevel' => 0,
            ],
            [
                'text'  => 'Paragraph text',
                'title' => '3. Überschrift 1',
                'files' => null,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '4. Überschrift 1',
                'files'        => null,
                'nestingLevel' => 0,
            ],
        ];

        $importResult = [
            'procedure'  => $procedureId,
            'elementId'  => $elementId,
            'category'   => 'paragraph',
            'paragraphs' => $paragraphs,
        ];
        $paragraphService = self::$container->get(ParagraphService::class);

        $procedureParagraphsBefore = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(3, $procedureParagraphsBefore);

        try {
            $this->sut->createParagraphsFromImportResult($importResult, $procedureId);
        } catch (ServiceImporterException $e) {
            static::assertCount(3, $e->getErrorParagraphs());
            static::assertEquals('2. Überschrift 1', $e->getErrorParagraphs()[1]);
        } catch (\Exception $e) {
            $this->fail();
        }

        $procedureParagraphsAfter = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(7, $procedureParagraphsAfter);
        static::assertNull($procedureParagraphsAfter[3]['parent']);
        static::assertNotNull($procedureParagraphsAfter[3]['children']);
        static::assertCount(1, $procedureParagraphsAfter[3]['children']);
        static::assertNotNull($procedureParagraphsAfter[4]['parent']);
        static::assertNotNull($procedureParagraphsAfter[4]['children']);
        static::assertNotNull($procedureParagraphsAfter[5]['parent']);
        static::assertNotNull($procedureParagraphsAfter[5]['children']);
        static::assertCount(0, $procedureParagraphsAfter[5]['children']);
        static::assertNull($procedureParagraphsAfter[6]['parent']);
        static::assertNotNull($procedureParagraphsAfter[6]['children']);
        static::assertCount(0, $procedureParagraphsAfter[6]['children']);
    }

    public function testParagraphImportCreateParagraphsWithNonSequentialHeadings()
    {
        $procedureId = $this->fixtures->getReference('testProcedure')->getId();
        $elementId = $this->fixtures->getReference('testElement1')->getId();

        $paragraphs = [
            [
                'text'         => 'Paragraph text',
                'title'        => '1. Überschrift 1',
                'files'        => null,
                'nestingLevel' => 0,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.1. Überschrift 1.1',
                'files'        => null,
                'nestingLevel' => 1,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.2. Überschrift 1.2',
                'files'        => null,
                'nestingLevel' => 1,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '1.2.1. Überschrift 1.2.1',
                'files'        => null,
                'nestingLevel' => 3,
            ],
            [
                'text'         => 'Paragraph text',
                'title'        => '2. Überschrift 1',
                'files'        => null,
                'nestingLevel' => 0,
            ],
        ];

        $importResult = [
            'procedure'  => $procedureId,
            'elementId'  => $elementId,
            'category'   => 'paragraph',
            'paragraphs' => $paragraphs,
        ];
        $paragraphService = self::$container->get(ParagraphService::class);

        $procedureParagraphsBefore = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(3, $procedureParagraphsBefore);

        try {
            $this->sut->createParagraphsFromImportResult($importResult, $procedureId);
        } catch (\Exception $e) {
            $this->fail();
        }

        $procedureParagraphsAfter = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(8, $procedureParagraphsAfter);
        static::assertNotNull($procedureParagraphsAfter[4]['parent']);
        static::assertEquals($procedureParagraphsAfter[3]['id'], $procedureParagraphsAfter[4]['parent']->getId());
        static::assertNotNull($procedureParagraphsAfter[6]['parent']);
        static::assertEquals($procedureParagraphsAfter[5]['id'], $procedureParagraphsAfter[6]['parent']->getId());
    }
}
