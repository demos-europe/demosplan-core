<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Document\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use Tests\Base\FunctionalTestCase;

class EditorServiceTest extends FunctionalTestCase
{
    /**
     * @var EditorService
     */
    protected $sut;

    /**
     * @var SingleDocument
     */
    protected $testDocument;

    /**
     * @var Procedure
     */
    protected $testProcedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(EditorService::class);
        $this->testDocument = $this->fixtures->getReference('testSingleDocument1');
        $this->testProcedure = $this->fixtures->getReference('testProcedure');
    }

    /**
     * @throws \demosplan\DemosPlanCoreBundle\Exception\MessageBagException
     */
    public function testReplaceAlternativeTextPlaceholderByHTMLTag(): void
    {
        $data = $this->createMockDataAltText();
        $textArray = [];

        // expected: data without editor alt tag but with html alt tag (for database)
        for ($i = 0; $i < $data['alt_texts_count']; ++$i) {
            if (!empty($data['alt_texts'][$i])) {
                $altTagHtml = $data['htmlAltTagOpen'].$data['alt_texts'][$i].$data['htmlAltTagClose'];
            } else {
                $altTagHtml = '';
            }

            $textArray[] =
                $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
                $data['editorImagePlaceholder'].
                $data['htmlImageOpeningTag'].
                '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
                $data['htmlImageWithHeightSettings'].
                $altTagHtml.
                $data['htmlImageClosingTag'].
                $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];
        }
        $expectedOutput = implode($textArray);
        $textArray = [];

        // actual input: data with alt text placeholder (from editor)
        for ($i = 0; $i < $data['alt_texts_count']; ++$i) {
            if (!empty($data['alt_texts'][$i])) {
                $altTagEditor = $data['editorAltTagOpen'].$data['alt_texts'][$i].$data['editorAltTagClose'];
            } else {
                $altTagEditor = '';
            }

            $textArray[] =
                $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
                $data['editorImagePlaceholder'].
                $altTagEditor.
                $data['htmlImageOpeningTag'].
                '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
                $data['htmlImageWithHeightSettings'].
                $data['htmlImageClosingTag'].
                $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];
        }
        $actualInput = implode($textArray);
        $actualOutput = $this->sut->replaceAlternativeTextPlaceholderByHTMLTag($actualInput);

        // correct editor alt text opening tag
        static::assertEquals($expectedOutput, $actualOutput, $actualInput);

        // incorrect editor alt text opening tag
        $actualInput = str_replace($data['editorAltTagOpen'], $data['editorAltTagOpenInaccurate'], $actualInput);
        $actualOutput = $this->sut->replaceAlternativeTextPlaceholderByHTMLTag($actualInput);
        static::assertEquals($expectedOutput, $actualOutput, $actualInput);
    }

    public function testSeparateTextIntoImageElements(): void
    {
        $imageCommentOpen = $this->sut::IMAGE_ID_OPENING_TAG;
        $imageCommentClose = $this->sut::IMAGE_ID_CLOSING_TAG;

        $text = 'fdsauofusdfsd';

        $string = $text.$text.$text.
            $imageCommentOpen.$imageCommentClose.$text.
            $imageCommentOpen.$imageCommentClose.$text.$text.
            $imageCommentOpen.$imageCommentClose.
            $imageCommentOpen.$imageCommentClose.$text.
            $imageCommentOpen.$imageCommentClose.$text.$text;

        $expectedOutput = [
            $text.$text.$text.$imageCommentOpen.$imageCommentClose,
            $text.$imageCommentOpen.$imageCommentClose,
            $text.$text.$imageCommentOpen.$imageCommentClose,
            $imageCommentOpen.$imageCommentClose,
            $text.$imageCommentOpen.$imageCommentClose,
            $text.$text,
        ];

        $actualOutput = $this->sut->separateTextFromEditorIntoImageElements($string);

        static::assertEquals($expectedOutput, $actualOutput);
    }

    public function testExtractAlternativeTextFromEditorText(): void
    {
        $mock = $this->createMockDataAltText();

        $myAlternativeText = $mock['alt_texts'][1];

        $textWithPlaceholder =
            $mock['unimportantTextBeforeImage'][0].$mock['unimportantTextBeforeImage'][0].
            $mock['editorImagePlaceholder'].
            $mock['editorAltTagOpen'].$myAlternativeText.$mock['editorAltTagClose'].
            $mock['htmlImageOpeningTag'].'0fe93361-4af8-11e9-bdf5-782bcb0d78b1'.
            $mock['htmlImageWithHeightSettings'].' '.$mock['htmlImageClosingTag'].
            $mock['unimportantTextAfterImage'][0].$mock['unimportantTextAfterImage'][0];

        $extractedAlternativeText = $this->sut->extractAlternativeTextFromEditorText($textWithPlaceholder);

        // correct editor alt text opening tag
        static::assertEquals($myAlternativeText, $extractedAlternativeText);

        // incorrect editor alt text opening tag
        $textWithPlaceholder = str_replace($mock['editorAltTagOpen'], $mock['editorAltTagOpenInaccurate'], $textWithPlaceholder);
        $extractedAlternativeText = $this->sut->extractAlternativeTextFromEditorText($textWithPlaceholder);
        static::assertEquals($myAlternativeText, $extractedAlternativeText);
    }

    public function testAlternativeTextExistsInStringFromEditorTrue(): void
    {
        $data = $this->createMockDataAltText();

        $i = 1;
        $textWithEditorAltTag =
            $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
            $data['editorImagePlaceholder'].
            $data['editorAltTagOpen'].'lalalala'.$data['editorAltTagClose'].
            $data['htmlImageOpeningTag'].
            '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
            $data['htmlImageWithHeightSettings'].
            $data['htmlImageClosingTag'].
            $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];

        // correct editor alt text opening tag
        $actualOutput = $this->sut->alternativeTextExistsInStringFromEditor($textWithEditorAltTag);
        static::assertTrue($actualOutput);

        // incorrect editor alt text opening tag
        $textWithEditorAltTag = str_replace($data['editorAltTagOpen'], $data['editorAltTagOpenInaccurate'], $textWithEditorAltTag);
        $actualOutput = $this->sut->alternativeTextExistsInStringFromEditor($textWithEditorAltTag);
        static::assertTrue($actualOutput);
    }

    public function testAlternativeTextExistsInStringFromEditorFalse(): void
    {
        $data = $this->createMockDataAltText();

        $i = 0;
        $textWithoutEditorAltTag =
            $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
            $data['editorImagePlaceholder'].
            $data['htmlImageOpeningTag'].
            '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
            $data['htmlImageWithHeightSettings'].
            $data['htmlImageClosingTag'].
            $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];

        // correct editor alt text opening tag
        $actualOutput = $this->sut->alternativeTextExistsInStringFromEditor($textWithoutEditorAltTag);
        static::assertFalse($actualOutput);

        // incorrect editor alt text opening tag
        $textWithoutEditorAltTag = str_replace($data['editorAltTagOpen'], $data['editorAltTagOpenInaccurate'], $textWithoutEditorAltTag);
        $actualOutput = $this->sut->alternativeTextExistsInStringFromEditor($textWithoutEditorAltTag);
        static::assertFalse($actualOutput);
    }

    public function testGetAlternativeTextPositionsArrayFromEditorTag(): void
    {
        // set up
        $mock = $this->createMockDataAltText();
        $beforeAltTag =
            $mock['unimportantTextBeforeImage'][0].$mock['unimportantTextBeforeImage'][0].
            $mock['editorImagePlaceholder'];
        $altTag = $mock['editorAltTagOpen'].$mock['alt_texts'][1].$mock['editorAltTagClose'];
        $afterAltTag =
            $mock['htmlImageOpeningTag'].'0fe93361-4af8-11e9-bdf5-782bcb0d78b1'.
            $mock['htmlImageWithHeightSettings'].' '.$mock['htmlImageClosingTag'].
            $mock['unimportantTextAfterImage'][0].$mock['unimportantTextAfterImage'][0];

        // how it should work
        $textWithEditorTag = $beforeAltTag.$altTag.$afterAltTag;
        $part1 = strlen($beforeAltTag.$mock['editorAltTagOpen']);
        $part2 = strlen($mock['alt_texts'][1]);
        $part1plus2 = $part1 + $part2;
        $expectedOutput = ['start' => $part1, 'end' => $part1plus2, 'length' => $part2];
        $actualOutput = $this->sut->getAlternativeTextPositionsArrayFromEditorTag($textWithEditorTag);
        static::assertEquals($expectedOutput, $actualOutput);

        // no alt text exists
        $textWithEditorTag = $beforeAltTag.$afterAltTag;
        $actualOutput = $this->sut->getAlternativeTextPositionsArrayFromEditorTag($textWithEditorTag);
        static::assertNull($actualOutput);
    }

    public function testGetAlternativeTextPositionsArrayFromHtmlTag(): void
    {
        $mockData = $this->createMockDataAltText();

        $dataSet = [
            [
                [
                    'start'  => strlen($mockData['htmlAltTagOpen']) + strlen($mockData['htmlAltTagClose']) + 94,
                    'end'    => 119,
                    'length' => 18,
                ],
                '<p>Sodann ein Bild3</p><p><!-- #Image-c8781b07-741b-4454-88c7-9ac0ed27a169&width='.
                '337&height=252'.$mockData['htmlAltTagOpen'].'my alt text2 img 3'.$mockData['htmlAltTagClose'].' --></p><p><b>Abbildung </b><b>1</b><b> mit text 2</b></p>',
            ],
            [
                [
                    'start'  => strlen($mockData['htmlAltTagOpen']) + strlen($mockData['htmlAltTagClose']) + 94,
                    'end'    => 102,
                    'length' => 1,
                ],
                '<p>Sodann ein Bild3</p><p><!-- #Image-c8781b07-741b-4454-88c7-9ac0ed27a169&width='.
                '337&height=252'.$mockData['htmlAltTagOpen'].'m'.$mockData['htmlAltTagClose'].' --></p><p><b>Abbildung </b><b>1</b><b> mit text 2</b></p>',
            ],
            [
                [
                    'start'  => strlen($mockData['htmlAltTagOpen']),
                    'end'    => strlen($mockData['htmlAltTagOpen']) + 1,
                    'length' => 1,
                ],
                $mockData['htmlAltTagOpen'].'m"',
            ],
        ];

        foreach ($dataSet as $data) { // This could also be done via dataProvider permission.
            $actualOutput = $this->sut->getAlternativeTextPositionsArrayFromHtmlTag($data[1]);

            static::assertEquals($data[0], $actualOutput);
        }
    }

    public function testRemoveAlternativeTextPlaceholder(): void
    {
        $mock = $this->createMockDataAltText();

        $myAlternativeText = $mock['alt_texts'][1];

        $beforeAltText = $mock['unimportantTextBeforeImage'][0].$mock['unimportantTextBeforeImage'][1];
        $afterAltText =
            $mock['htmlImageOpeningTag'].'0fe93361-4af8-11e9-bdf5-782bcb0d78b1'.
            $mock['htmlImageWithHeightSettings'].' '.$mock['htmlImageClosingTag'].
            $mock['unimportantTextAfterImage'][0].$mock['unimportantTextAfterImage'][1];

        $expectedOutput = $beforeAltText.$afterAltText;
        $actualInput =
            $beforeAltText.
            $mock['editorAltTagOpen'].$mock['alt_texts'][1].$mock['editorAltTagClose'].
            $afterAltText;

        // correct editor alt text opening tag
        $actualOutput = $this->sut->removeEditorAlternativeTextPlaceholder($actualInput, $myAlternativeText);
        static::assertEquals($expectedOutput, $actualOutput);

        // incorrect editor alt text opening tag
        $actualInput = str_replace($mock['editorAltTagOpen'], $mock['editorAltTagOpenInaccurate'], $actualInput);
        $actualOutput = $this->sut->removeEditorAlternativeTextPlaceholder($actualInput, $myAlternativeText);
        static::assertEquals($expectedOutput, $actualOutput);
    }

    public function testSetAlternativeTextHTMLTag(): void
    {
        $mockData = $this->createMockDataAltText();
        $i = 0;
        $altText = 'fsdakbfgsa';

        $expectedOutput =
            $mockData['unimportantTextBeforeImage'][0].$i.$mockData['unimportantTextBeforeImage'][1].
            $mockData['htmlImageOpeningTag'].
            '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
            $mockData['htmlImageWithHeightSettings'].
            '&alt="'.$altText.'"'.
            $mockData['htmlImageClosingTag'].
            $mockData['unimportantTextAfterImage'][0].$i.$mockData['unimportantTextAfterImage'][1];

        $textWithoutHtmlTag =
            $mockData['unimportantTextBeforeImage'][0].$i.$mockData['unimportantTextBeforeImage'][1].
            $mockData['htmlImageOpeningTag'].
            '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
            $mockData['htmlImageWithHeightSettings'].
            $mockData['htmlImageClosingTag'].
            $mockData['unimportantTextAfterImage'][0].$i.$mockData['unimportantTextAfterImage'][1];

        // correct editor alt text opening tag
        $actualOutput = $this->sut->setAlternativeTextHTMLCommentTag($textWithoutHtmlTag, $altText);
        static::assertEquals($expectedOutput, $actualOutput);

        // incorrect editor alt text opening tag
        $textWithoutHtmlTag = str_replace($mockData['editorAltTagOpen'], $mockData['editorAltTagOpenInaccurate'], $textWithoutHtmlTag);
        $actualOutput = $this->sut->setAlternativeTextHTMLCommentTag($textWithoutHtmlTag, $altText);
        static::assertEquals($expectedOutput, $actualOutput);
    }

    public function testAddImagePlaceholdersToStringFromDatabase(): void
    {
        $data = $this->createMockDataAltText();
        $textArray = [];

        // expected: string from database without image placeholder
        for ($i = 0; $i < $data['alt_texts_count']; ++$i) {
            if (!empty($data['alt_texts'][$i])) {
                $altTagHtml = $data['htmlAltTagOpen'].$data['alt_texts'][$i].$data['htmlAltTagClose'];
            } else {
                $altTagHtml = '';
            }

            $textArray[] =
                $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
                $data['editorImagePlaceholder'].
                $data['htmlImageOpeningTag'].
                '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
                $data['htmlImageWithHeightSettings'].
                $altTagHtml.
                $data['htmlImageClosingTag'].
                $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];
        }
        $expectedOutput = implode($textArray);
        $textArray = [];

        // expected: string from database with image placeholder
        for ($i = 0; $i < $data['alt_texts_count']; ++$i) {
            if (!empty($data['alt_texts'][$i])) {
                $altTagHtml = $data['htmlAltTagOpen'].$data['alt_texts'][$i].$data['htmlAltTagClose'];
            } else {
                $altTagHtml = '';
            }

            $textArray[] =
                $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
                $data['htmlImageOpeningTag'].
                '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
                $data['htmlImageWithHeightSettings'].
                $altTagHtml.
                $data['htmlImageClosingTag'].
                $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];
        }
        $actualInput = implode($textArray);
        $actualOutput = $this->sut->addImagePlaceholdersToStringFromDatabase($actualInput);

        static::assertEquals($expectedOutput, $actualOutput);
    }

    public function testReplaceHtmlAltTextTagByAlternativeTextPlaceholder(): void
    {
        $data = $this->createMockDataAltText();

        // expected: data with editor alt tag but without html alt tag (for editor)
        for ($i = 0; $i < $data['alt_texts_count']; ++$i) {
            if (!empty($data['alt_texts'][$i])) {
                $altTagEditor = $data['editorAltTagOpen'].$data['alt_texts'][$i].$data['editorAltTagClose'];
            } else {
                $altTagEditor = $data['editorAltTagOpen'].$data['htmlAlternativeTextPlaceholder'].$data['editorAltTagClose'];
            }

            $textArray[] =
                $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
                $data['editorImagePlaceholder'].
                $altTagEditor.
                $data['htmlImageOpeningTag'].
                '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
                $data['htmlImageWithHeightSettings'].
                $data['htmlImageClosingTag'].
                $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];
        }
        $expectedOutput = implode($textArray);
        unset($textArray);

        // actual input: data with alt text html placeholder (from database)
        for ($i = 0; $i < $data['alt_texts_count']; ++$i) {
            if (!empty($data['alt_texts'][$i])) {
                $altTagHtml = $data['htmlAltTagOpen'].$data['alt_texts'][$i].$data['htmlAltTagClose'];
            } else {
                $altTagHtml = '';
            }

            $textArray[] =
                $data['unimportantTextBeforeImage'][0].$i.$data['unimportantTextBeforeImage'][1].
                $data['editorImagePlaceholder'].
                $data['htmlImageOpeningTag'].
                '0fe93361-4af8-11e9-bdf5-782bcb0d78b'.$i.
                $data['htmlImageWithHeightSettings'].
                $altTagHtml.
                $data['htmlImageClosingTag'].
                $data['unimportantTextAfterImage'][0].$i.$data['unimportantTextAfterImage'][1];
        }
        $actualInput = implode($textArray);
        $actualOutput = $this->sut->replaceHtmlAltTextTagByAlternativeTextPlaceholder($actualInput);

        static::assertEquals($expectedOutput, $actualOutput, $actualInput);
    }

    public function testObscureString(): void
    {
        $simple = 'VW liefert ausschliesslich aufwaendig getestete Abgasanlagen aus<dp-obscure>, nachdem ihre Messwerte fachmaennisch verfaelscht wurden</dp-obscure>.';
        $simpleRes = 'VW liefert ausschliesslich aufwaendig getestete Abgasanlagen aus█ ███████ ████ █████████ █████████████ ███████████ ██████.';

        $crossed = 'VW liefert <dp-obscure>ausschliesslich aufwaendig getestete Abgasanlagen aus<dp-obscure>, nachdem</dp-obscure> ihre Messwerte fachmaennisch verfaelscht wurden</dp-obscure>.';
        $crossedRes = 'VW liefert ███████████████ ██████████ █████████ ████████████ ████ ███████ ████ █████████ █████████████ ███████████ ██████.';

        $html = 'VW liefert ausschliesslich aufwaendig getestete <b>Abgasanlagen</b> aus<dp-obscure>, nachdem <i>ihre</i> Messwerte fachmaennisch verfaelscht wurden</dp-obscure>.';
        $htmlRes = 'VW liefert ausschliesslich aufwaendig getestete <b>Abgasanlagen</b> aus█ ███████ ███████████ █████████ █████████████ ███████████ ██████.';

        $htmlCrossed = 'VW liefert ausschliesslich aufwaendig getestete <b>Abgasanlagen aus<dp-obscure>, nachdem <i>ihre</i> Messwerte</b> fachmaennisch verfaelscht wurden</dp-obscure>.';
        $htmlCrossedRes = 'VW liefert ausschliesslich aufwaendig getestete <b>Abgasanlagen aus█ ███████ ███████████ █████████████ █████████████ ███████████ ██████.';

        $two = 'VW liefert <dp-obscure>ausschliesslich aufwaendig getestete Abgasanlagen aus</dp-obscure>, nachdem<dp-obscure> ihre Messwerte fachmaennisch verfaelscht wurden</dp-obscure>.';
        $twoRes = 'VW liefert ███████████████ ██████████ █████████ ████████████ ███, nachdem ████ █████████ █████████████ ███████████ ██████.';

        $three = '<li><dp-obscure>Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</dp-obscure></li>';
        $threeRes = '<li>█████ █████ █████ ███ █████ ████████████ ██████████ █████</li>';

        $four = '<li><dp-obscure>Aenean commodo ligula eget dolor. Aenean massa.</dp-obscure></li>';
        $fourRes = '<li>██████ ███████ ██████ ████ ██████ ██████ ██████</li>';

        $resultString = $this->sut->obscureString($simple);
        static::assertEquals($simpleRes, $resultString);

        $resultString = $this->sut->obscureString($crossed);
        static::assertEquals($crossedRes, $resultString);

        $resultString = $this->sut->obscureString($html);
        static::assertEquals($htmlRes, $resultString);

        $resultString = $this->sut->obscureString($htmlCrossed);
        static::assertEquals($htmlCrossedRes, $resultString);

        $resultString = $this->sut->obscureString($two);
        static::assertEquals($twoRes, $resultString);

        $resultString = $this->sut->obscureString($three);
        static::assertEquals($threeRes, $resultString);

        $resultString = $this->sut->obscureString($four);
        static::assertEquals($fourRes, $resultString);
    }

    public function testObscureString2(): void
    {
        $stringToObscure = 'thisTextShouldBeReplaced';
        $sut = $this->sut;
        $usedOpeningTag = $sut::OBSCURE_TAG_OPEN;
        $usedClosingTag = $sut::OBSCURE_TAG_CLOSE;
        $someString1 = '123';
        $someString2 = '456';

        $shouldBeUnchanged = $this->sut->obscureString($stringToObscure);
        static::assertEquals($stringToObscure, $shouldBeUnchanged);

        $text = $someString1.$usedOpeningTag.$stringToObscure.$usedClosingTag.$someString2;
        $expectedLengthOfResultString = strlen($stringToObscure) + strlen($someString1) + strlen($someString2);
        $result = $this->sut->obscureString($text);

        $obscuredLetters = '';
        for ($i = 1, $iMax = strlen($stringToObscure); $i <= $iMax; ++$i) {
            $obscuredLetters .= '█';
        }
        $expectedResult = '123'.$obscuredLetters.'456';

        static::assertEquals($expectedLengthOfResultString, mb_strlen($result), 'resulting String has not same length as inputString');
        static::assertEquals($expectedResult, $result);
    }

    /**
     * Creates mock data for alternative text tag tests.
     */
    protected function createMockDataAltText(): array
    {
        $mockData = [
            'editorImagePlaceholder'         => $this->sut::EDITOR_IMAGE_PLACEHOLDER,
            'htmlAlternativeTextPlaceholder' => $this->sut::HTML_ALTERNATIVE_TEXT_PLACEHOLDER,
            'htmlImageWithHeightSettings'    => '&width=337&height=252',

            'unimportantTextBeforeImage'     => ['<p>Sodann ein Bild ', '</p>'],
            'unimportantTextAfterImage'      => ['</p><p><b>Abbildung </b><b>', '</b><b> Ich bin die Superblume</b></p>'],

            'htmlImageOpeningTag'            => $this->sut::IMAGE_ID_OPENING_TAG,
            'htmlImageClosingTag'            => $this->sut::IMAGE_ID_CLOSING_TAG,

            'editorAltTagOpen'               => $this->sut::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN,
            'editorAltTagOpenInaccurate'     => $this->sut::EDITOR_ALTERNATIVE_TEXT_TAG_OPEN_INACCURATE,
            'editorAltTagClose'              => $this->sut::EDITOR_ALTERNATIVE_TEXT_TAG_CLOSE,

            'htmlAltTagOpen'                 => $this->sut::HTML_ALTERNATIVE_TEXT_TAG_OPEN,
            'htmlAltTagClose'                => $this->sut::HTML_ALTERNATIVE_TEXT_TAG_CLOSE,

            'alt_texts'                      => [
                '',
                'my alt text',
                'second actual text',
                '',
                'final text!',
                '',
                '',
                'helloooooooooooo text',
            ],
            'alt_texts_count'                => 8,
        ];

        return $mockData;
    }
}
