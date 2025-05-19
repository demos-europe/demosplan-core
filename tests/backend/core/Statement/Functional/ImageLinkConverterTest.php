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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ImageReference;
use Tests\Base\FunctionalTestCase;

class ImageLinkConverterTest extends FunctionalTestCase
{
    /**
     * @var ImageLinkConverter
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $fileService = $this->createPartialMock(FileService::class, ['ensureLocalFileFromHash']);
        $fileService->method('ensureLocalFileFromHash')->willReturnCallback(
            fn ($hash) => '/absolute/path/to/'.$hash
        );

        /** @var HtmlHelper $htmlHelper */
        $htmlHelper = $this->getContainer()->get(HtmlHelper::class);

        /** @var EditorService $editorService */
        $editorService = $this->getContainer()->get(EditorService::class);
        $this->sut = new ImageLinkConverter($htmlHelper, $fileService, $editorService);
    }

    public function testObscureSegmentText()
    {
        $segment = SegmentFactory::createOne(
            ['text' => '<p>Clear text and this is <dp-obscure>obscure</dp-obscure> text</p>']
        );
        $expectedSegmentText = '<p>Clear text and this is ███████ text</p>';
        $result = $this->sut->convert($segment->_real(), $segment->getId(), false, true);

        static::assertSame($expectedSegmentText, $result->getText());
    }

    public function testConvertWithLinkedReference(): void
    {
        $segment = $this->createTestSegment();

        $statementExternId = 'statement123';
        $linkStyle = 'style="color: blue; text-decoration: underline;"';
        $linkStartRecommendation = '<a href="#'.$statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX;
        $linkStartSegmentText = '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '" href="#'.$statementExternId.ImageLinkConverter::IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX;
        $linkEnd = '" '.$linkStyle.'>';
        $linkClose = '</a>';

        $expectedSegmentText = '<p>Some text '.$linkStartSegmentText.'001'.$linkEnd.
            $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX.'001'.$linkClose.
            ' more text <a href="path/to/image2.jpg">image2</a>'.
            ' and '.$linkStartSegmentText.'002'.$linkEnd.
            $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX.'002'.$linkClose.'</p>';

        $expectedRecommendation = '<p>Some text '.$linkStartRecommendation.'001'.$linkEnd.
            $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'001'.$linkClose.
            ' more text '.$linkStartRecommendation.'002'.$linkEnd.
            $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'002'.$linkClose.'</p>';

        $result = $this->sut->convert($segment, $statementExternId);

        static::assertSame($expectedSegmentText, $result->getText());
        static::assertSame($expectedRecommendation, $result->getRecommendationText());
    }

    public function testConvertWithoutLinkedReference(): void
    {
        $segment = $this->createTestSegment();

        $statementExternId = 'statement123';
        $expectedSegmentText = '<p>Some text '.$statementExternId.ImageLinkConverter::IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX.'001'.
            ' more text <a href="path/to/image2.jpg">image2</a> and '.
            $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX.'002</p>';
        $expectedRecommendation = '<p>Some text '.$statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'001'.
            ' more text '.$statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'002</p>';
        $result = $this->sut->convert($segment, $statementExternId, false);

        static::assertSame($expectedSegmentText, $result->getText());
        static::assertSame($expectedRecommendation, $result->getRecommendationText());
    }

    public function testGetImages(): void
    {
        $segment = $this->createTestSegment();
        $statementExternId = 'statement123';

        $this->sut->convert($segment, $statementExternId);

        $keyImage1 = $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX.'001';
        $keyImage3 = $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_SEGMENT_TEXT_SUFFIX.'002';
        $keyImage4 = $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'001';
        $keyImage5 = $statementExternId.ImageLinkConverter::IMAGE_REFERENCE_RECOMMENDATION_SUFFIX.'002';
        $imagePath1 = '/absolute/path/to/image1.jpg';
        $imagePath3 = '/absolute/path/to/image3.jpg';
        $imagePath4 = '/absolute/path/to/image4.jpg';
        $imagePath5 = '/absolute/path/to/image5.jpg';
        $expectedImage1 = new ImageReference($keyImage1, $imagePath1);
        $expectedImage3 = new ImageReference($keyImage3, $imagePath3);
        $expectedImage4 = new ImageReference($keyImage4, $imagePath4);
        $expectedImage5 = new ImageReference($keyImage5, $imagePath5);

        $result = $this->sut->getImages();

        static::assertCount(1, $result);
        static::assertInstanceOf(
            ImageReference::class,
            $result[0][ImageLinkConverter::IMAGES_KEY_RECOMMENDATION][0]
        );
        static::assertInstanceOf(
            ImageReference::class,
            $result[0][ImageLinkConverter::IMAGES_KEY_RECOMMENDATION][1]
        );
        [$result1, $result2] = $result[0][ImageLinkConverter::IMAGES_KEY_SEGMENTS];
        [$result3, $result4] = $result[0][ImageLinkConverter::IMAGES_KEY_RECOMMENDATION];
        static::assertSame($expectedImage1->getImageReference(), $result1->getImageReference());
        static::assertSame($expectedImage1->getImagePath(), $result1->getImagePath());
        static::assertSame($expectedImage3->getImageReference(), $result2->getImageReference());
        static::assertSame($expectedImage3->getImagePath(), $result2->getImagePath());
        static::assertSame($expectedImage4->getImageReference(), $result3->getImageReference());
        static::assertSame($expectedImage4->getImagePath(), $result3->getImagePath());
        static::assertSame($expectedImage5->getImageReference(), $result4->getImageReference());
        static::assertSame($expectedImage5->getImagePath(), $result4->getImagePath());
    }

    public function testResetImages(): void
    {
        $segment = $this->createTestSegment();
        $statementExternId = 'statement123';

        $this->sut->convert($segment, $statementExternId);
        $this->sut->resetImages();

        static::assertEmpty($this->sut->getImages());
    }

    private function createTestSegment(): Segment
    {
        /** @var Segment $segment */
        $segment = SegmentFactory::createOne()->_real();
        $link1 = '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '" href="path/to/image1.jpg">Darstellung_Stell_001</a>';
        $link2 = '<a href="path/to/image2.jpg">image2</a>';
        $link3 = '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '" href="path/to/image3.jpg">Darstellung_Stell_002</a>';
        $text = '<p>Some text '.$link1.' more text '.$link2.' and '.$link3.'</p>';
        $recommendation = '<p>Some text <img src="path/to/image4.jpg" /> more text <img src="path/to/image5.jpg" /></p>';
        $segment->setRecommendation($recommendation);
        $segment->setText($text);

        return $segment;
    }
}
