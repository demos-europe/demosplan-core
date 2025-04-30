<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Unit;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ServiceImporterException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ParagraphService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphRepository;
use demosplan\DemosPlanCoreBundle\Tools\DocxImporterInterface;
use demosplan\DemosPlanCoreBundle\Tools\PdfCreatorInterface;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Exception;
use League\Flysystem\FilesystemOperator;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Base\FunctionalTestCase;

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
            self::getContainer()->get(DocxImporterInterface::class),
            self::getContainer()->get(FileService::class),
            $this->createMock(FilesystemOperator::class),
            self::getContainer()->get(GlobalConfigInterface::class),
            self::getContainer()->get(LoggerInterface::class),
            self::getContainer()->get(MessageBagInterface::class),
            self::getContainer()->get(ParagraphRepository::class),
            self::getContainer()->get(ParagraphService::class),
            self::getContainer()->get(PdfCreatorInterface::class),
            self::getContainer()->get(RouterInterface::class),
            self::getContainer()->get(RpcClient::class),
            self::getContainer()->get(EventDispatcherInterface::class),
        );
    }

    public function testAllowedFileUploadFail(): void
    {
        $this->expectException(FileException::class);

        $fileInfo = new FileInfo(
            hash: 'someHash',
            fileName: 'myFile.zip',
            fileSize: 12345,
            contentType: 'not/allowed',
            path: '/path/to/file',
            absolutePath: '/path/to/file',
            procedure: null
        );

        $this->sut->checkFileIsValidToImport($fileInfo);
    }

    public function testParagraphImportCreateParagraphs(): void
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
        $paragraphService = self::getContainer()->get(ParagraphService::class);

        $procedureParagraphsBefore = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(0, $procedureParagraphsBefore);

        $this->sut->createParagraphsFromImportResult($importResult, $procedureId);

        $procedureParagraphsAfter = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(5, $procedureParagraphsAfter);
        static::assertNull($procedureParagraphsAfter[0]['parent']);
        static::assertNotNull($procedureParagraphsAfter[0]['children']);
        static::assertCount(2, $procedureParagraphsAfter[0]['children']);
        static::assertNotNull($procedureParagraphsAfter[1]['parent']);
        static::assertNotNull($procedureParagraphsAfter[1]['children']);
        static::assertNotNull($procedureParagraphsAfter[2]['parent']);
        static::assertNotNull($procedureParagraphsAfter[2]['children']);
        static::assertCount(1, $procedureParagraphsAfter[2]['children']);
        static::assertNotNull($procedureParagraphsAfter[3]['parent']);
        static::assertNotNull($procedureParagraphsAfter[3]['children']);
        static::assertNull($procedureParagraphsAfter[4]['parent']);
        static::assertNotNull($procedureParagraphsAfter[4]['children']);
        static::assertCount(0, $procedureParagraphsAfter[4]['children']);
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
        $paragraphService = self::getContainer()->get(ParagraphService::class);

        $procedureParagraphsBefore = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(0, $procedureParagraphsBefore);

        try {
            $this->sut->createParagraphsFromImportResult($importResult, $procedureId);
        } catch (ServiceImporterException $e) {
            static::assertCount(3, $e->getErrorParagraphs());
            static::assertEquals('2. Überschrift 1', $e->getErrorParagraphs()[1]);
        } catch (Exception $e) {
            $this->fail();
        }

        $procedureParagraphsAfter = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(4, $procedureParagraphsAfter);
        static::assertNull($procedureParagraphsAfter[0]['parent']);
        static::assertNotNull($procedureParagraphsAfter[0]['children']);
        static::assertCount(1, $procedureParagraphsAfter[0]['children']);
        static::assertNotNull($procedureParagraphsAfter[1]['parent']);
        static::assertNotNull($procedureParagraphsAfter[1]['children']);
        static::assertNotNull($procedureParagraphsAfter[2]['parent']);
        static::assertNotNull($procedureParagraphsAfter[2]['children']);
        static::assertCount(0, $procedureParagraphsAfter[2]['children']);
        static::assertNull($procedureParagraphsAfter[3]['parent']);
        static::assertNotNull($procedureParagraphsAfter[3]['children']);
        static::assertCount(0, $procedureParagraphsAfter[3]['children']);
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
        $paragraphService = self::getContainer()->get(ParagraphService::class);

        $procedureParagraphsBefore = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(0, $procedureParagraphsBefore);

        try {
            $this->sut->createParagraphsFromImportResult($importResult, $procedureId);
        } catch (Exception $e) {
            $this->fail();
        }

        $procedureParagraphsAfter = $paragraphService->getParaDocumentList($procedureId, $elementId);
        static::assertCount(5, $procedureParagraphsAfter);
        static::assertNotNull($procedureParagraphsAfter[1]['parent']);
        static::assertEquals($procedureParagraphsAfter[0]['id'], $procedureParagraphsAfter[1]['parent']->getId());
        static::assertNotNull($procedureParagraphsAfter[3]['parent']);
        static::assertEquals($procedureParagraphsAfter[2]['id'], $procedureParagraphsAfter[3]['parent']->getId());
    }

    public function testParagraphImportCreateParagraphsWithPicture(): void
    {
        $procedureId = $this->fixtures->getReference('testProcedure')->getId();
        $elementId = $this->fixtures->getReference('testElement1')->getId();
        $paragraphs = [
            [
                'text'         => "<p>Standard Absatztext</p><p><img src='/file/khaeti4c3lmqpjfnqtped251ka' width='0' height='0'>nochmal</p><p><img src='/file/ha3qod8qfr0s413ghve7vqqeqi' width='0' height='0'></p>",
                'title'        => 'Meine Überschrift',
                'files'        => [
                    ['##khaeti4c3lmqpjfnqtped251ka' => 'khaeti4c3lmqpjfnqtped251ka.png::iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC'],
                    ['##ha3qod8qfr0s413ghve7vqqeqi' => 'ha3qod8qfr0s413ghve7vqqeqi.png::iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC'],
                ],
                'nestingLevel' => 0,
            ],
        ];

        $importResult = [
            'procedure'  => $procedureId,
            'elementId'  => $elementId,
            'category'   => 'paragraph',
            'paragraphs' => $paragraphs,
        ];
        $paragraphService = $this->getContainer()->get(ParagraphService::class);
        $this->sut->createParagraphsFromImportResult($importResult, $procedureId);

        $procedureParagraphsAfter = $paragraphService->getParaDocumentObjectList($procedureId, $elementId);
        // check only for the width and height as the hash always differs
        $expectedPart = "width='0' height='0'";
        static::assertStringContainsString($expectedPart, $procedureParagraphsAfter[count($procedureParagraphsAfter) - 1]->getText());
    }
}
