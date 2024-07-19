<?php

/** @noinspection PhpUnitMissingTargetForTestInspection */
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Tests\Base\FunctionalTestCase;

class ImageLinkConverterTest extends FunctionalTestCase
{
    /**
     * @var ImageLinkConverter
     */
    protected $sut;

    protected function setUp(): void
    {
        $fileService = $this->createMock(FileService::class);
        $fileService->method('getFileInfo')->willReturnCallback(
            fn ($hash) => new FileInfo(
                $hash,
                'filename.jpg',
                12345,
                'image/jpeg',
                '/path/to/file',
                '/absolute/path/to/'.$hash,
                $this->createMock(Procedure::class)
            )
        );
        $this->sut = new ImageLinkConverter($fileService);
    }

    public function testConvertWithLinkedReference(): void
    {
        $html = '<p>Some text <img src="path/to/image1.jpg" /> more text <img src="path/to/image2.jpg" /></p>';
        $statementExternId = 'statement123';

        $linkStyle = 'style="color: blue; text-decoration: underline;"';
        $linkStart = '<a href="#statement123'.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX;
        $linkEnd = '" '.$linkStyle.'>';
        $linkClose = '</a>';
        $expected = '<p>Some text '.$linkStart.'001'.$linkEnd.
            'statement123'.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'001'.$linkClose.
            ' more text '.$linkStart.'002'.$linkEnd.
            'statement123'.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'002'.$linkClose.'</p>';
        $result = $this->sut->convert($html, $statementExternId);

        static::assertSame($expected, $result);
    }

    public function testConvertWithoutLinkedReference(): void
    {
        $html = '<p>Some text <img src="path/to/image1.jpg" /> more text <img src="path/to/image2.jpg" /></p>';
        $statementExternId = 'statement123';

        $expected = '<p>Some text statement123'.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'001'.
            ' more text statement123'.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'002</p>';
        $result = $this->sut->convert($html, $statementExternId, false);

        static::assertSame($expected, $result);
    }

    public function testGetImages(): void
    {
        $html = '<p>Some text <img src="path/to/image1.jpg" /> more text <img src="path/to/image2.jpg" /></p>';
        $statementExternId = 'statement123';

        $this->sut->convert($html, $statementExternId);

        $keyImage1 = $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'001';
        $keyImage2 = $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'002';
        $expectedImages = [
            $keyImage1 => '/absolute/path/to/image1.jpg',
            $keyImage2 => '/absolute/path/to/image2.jpg',
        ];

        static::assertSame($expectedImages, $this->sut->getImages());
    }

    public function testResetImages(): void
    {
        $html = '<p>Some text <img src="path/to/image1.jpg" /></p>';
        $statementExternId = 'statement123';

        $this->sut->convert($html, $statementExternId);
        $this->sut->resetImages();

        static::assertEmpty($this->sut->getImages());
    }
}
