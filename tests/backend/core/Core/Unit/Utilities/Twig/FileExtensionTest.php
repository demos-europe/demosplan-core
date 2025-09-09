<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Twig\Extension\FileExtension;
use Exception;
use stdClass;
use Tests\Base\UnitTestCase;
use Twig\Environment;
use Twig\TwigFilter;

/**
 * Teste GetFileExtension
 * Class GetFileExtensionTest.
 *
 * @group UnitTest
 */
class FileExtensionTest extends UnitTestCase
{
    /**
     * @var FileExtension
     */
    private $twigExtension;

    /**
     * Set up Test.
     *
     * @return void|null
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new FileExtension(
            self::getContainer(),
            self::getContainer()->get(Environment::class),
            self::getContainer()->get(FileService::class)
        );
    }

    /**
     * Test get Filters.
     */
    public function testGetFilters()
    {
        try {
            $result = $this->twigExtension->getFilters();
            static::assertTrue(is_array($result) && isset($result[0]));
            static::assertInstanceOf(TwigFilter::class, $result[0]);
            static::assertSame('getFile', $result[0]->getName());
        } catch (Exception $e) {
            static::assertTrue(false);

            return;
        }
    }

    /**
     * Test getInfo.
     */
    public function testGetInfo()
    {
        try {
            $textToTest = '';
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue('' === $result);

            $textToTest = null;
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue('' === $result);

            $textToTest = false;
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue('' === $result);

            $textToTest = true;
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue('' === $result);

            $textToTest = [];
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue('' === $result);

            $textToTest = ['something'];
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue('' === $result);

            $textToTest = new stdClass();
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue('' === $result);

            $textToTest = 'Easy String';
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertTrue($result === $textToTest);
            $textToTest = 'Easy String:Something';
            $expectedResult = 'Easy String';
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertEquals($expectedResult, $result);
            $textToTest = 'Easy String:Something:Something';
            $expectedResult = 'Easy String';
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertEquals($expectedResult, $result);
            $textToTest = 'Easy String:Something:Something:Something';
            $expectedResult = 'Easy String';
            $result = $this->twigExtension->getFileFilter($textToTest, 'name');
            static::assertEquals($expectedResult, $result);

            $textToTest = '';
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertTrue('' === $result);

            $textToTest = null;
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertTrue('' === $result);

            $textToTest = false;
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertTrue('' === $result);

            $textToTest = true;
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertTrue('' === $result);

            $textToTest = [];
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertTrue('' === $result);

            $textToTest = ['something'];
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertTrue('' === $result);

            $textToTest = new stdClass();
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertTrue('' === $result);

            $textToTest = 'Easy String';
            $expectedResult = '';
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertEquals($expectedResult, $result);
            $textToTest = 'Easy String:Hash';
            $expectedResult = 'Hash';
            $result = $this->twigExtension->getFileFilter($textToTest, 'hash');
            static::assertEquals($expectedResult, $result);

            $textToTest = '';
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertTrue('' === $result);

            $textToTest = null;
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertTrue('' === $result);

            $textToTest = false;
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertTrue('' === $result);

            $textToTest = true;
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertTrue('' === $result);

            $textToTest = [];
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertTrue('' === $result);

            $textToTest = ['something'];
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertTrue('' === $result);

            $textToTest = new stdClass();
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertTrue('' === $result);

            $textToTest = 'Easy String';
            $expectedResult = '';
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Hash:1000';
            $expectedResult = '0.98 KB';
            $result = $this->twigExtension->getFileFilter($textToTest, 'size');
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Hash:1000';
            $expectedResult = '1000 B';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'size',
                'B'
            );
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Hash:10000';
            $expectedResult = '9.77 KB';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'size',
                'KB'
            );
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Hash:1000000';
            $expectedResult = '0.95 MB';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'size',
                'MB'
            );
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Hash:10000000000';
            $expectedResult = '9.31 GB';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'size',
                'GB'
            );
            static::assertEquals($expectedResult, $result);

            $textToTest = '';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertTrue('' === $result);

            $textToTest = null;
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertTrue('' === $result);

            $textToTest = false;
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertTrue('' === $result);

            $textToTest = true;
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertTrue('' === $result);

            $textToTest = [];
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertTrue('' === $result);

            $textToTest = ['something'];
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertTrue('' === $result);

            $textToTest = new stdClass();
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertTrue('' === $result);

            $textToTest = 'Easy String';
            $expectedResult = '';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Something';
            $expectedResult = '';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Something:Something';
            $expectedResult = '';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertEquals($expectedResult, $result);

            $textToTest = 'Easy String:Something:Something:application/pdf';
            $expectedResult = 'pdf';
            $result = $this->twigExtension->getFileFilter(
                $textToTest,
                'mimeType'
            );
            static::assertEquals($expectedResult, $result);
        } catch (Exception $e) {
            static::assertTrue(false);

            return;
        }
    }

    /**
     * Test name.
     */
    public function testName()
    {
        $result = $this->twigExtension->getName();
        static::assertTrue('file_extension' === $result);
    }

    /**
     * @dataProvider  filesizesDataProvider
     */
    public function testFormatHumanFileSize($size, $expected)
    {
        static::assertEquals($expected, $this->twigExtension->formatHumanFilesize($size));
    }

    public function filesizesDataProvider()
    {
        return [
            [1, '1 B'],
            [99, '99 B'],
            [100, '100 B'],
            [110, '110 B'],
            [999, '999 B'],
            [1000, '1000 B'],
            [1100, '1.07 KB'],
            [9999, '9.76 KB'],
            [10000, '9.77 KB'],
            [11000, '10.74 KB'],
            [11000000, '10.49 MB'],
            [11000253000, '10.24 GB'],
        ];
    }

    public function shorthandValuesDataProvider()
    {
        return [
            ['1k', 1024],
            ['2M', 2097152],
            [30, 30],
            [4000, 4000],
        ];
    }

    /**
     * @dataProvider shortHandValuesDataProvider
     */
    public function testConvertPhpiniShorthandvaluesToBytes($input, $expected)
    {
        static::assertEquals($expected, $this->twigExtension->convertPhpiniShorthandvaluesToBytes($input));
    }
}
