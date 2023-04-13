<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AssessmentTable\Functional;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\KeysAtEndSorter;
use demosplan\DemosPlanCoreBundle\Logic\Grouping\StatementEntityGroup;
use Tests\Base\FunctionalTestCase;
use TypeError;

class KeysAtEndSorterTest extends FunctionalTestCase
{
    public function testSortArrayInvalidNull()
    {
        $this->expectException(TypeError::class);
        $sorter = new KeysAtEndSorter([1, 2, 3]);
        $sorter->sortArray(6);
    }

    public function testSortArrayInvalidNumber()
    {
        $this->expectException(TypeError::class);
        $sorter = new KeysAtEndSorter([1, 2, 3]);
        $sorter->sortArray('abc');
    }

    public function testSortArrayInvalidString()
    {
        $this->expectException(TypeError::class);
        $sorter = new KeysAtEndSorter([1, 2, 3]);
        $sorter->sortArray(null);
    }

    public function testConstructInvalidNull()
    {
        $this->expectException(TypeError::class);
        new KeysAtEndSorter(null);
    }

    public function testConstructInvalidNumber()
    {
        $this->expectException(TypeError::class);
        new KeysAtEndSorter(6);
    }

    public function testConstructInvalidString()
    {
        $this->expectException(TypeError::class);
        new KeysAtEndSorter('abc');
    }

    public function testConstructInvalidEmpty()
    {
        $this->expectException(InvalidArgumentException::class);
        new KeysAtEndSorter([]);
    }

    public function testSortArrayValidKeysNumbers()
    {
        $sorter = new KeysAtEndSorter([1, 2, 3]);

        $unsortedGroups = $this->getUnsortedGroupsNumbered();

        $expectedGroups = [
            0 => $unsortedGroups[0],
            4 => $unsortedGroups[4],
            1 => $unsortedGroups[1],
            2 => $unsortedGroups[2],
            3 => $unsortedGroups[3],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysStrings()
    {
        $sorter = new KeysAtEndSorter(['b', 'c', '']);

        $unsortedGroups = $this->getUnsortedGroupsKeys();

        $expectedGroups = [
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
            'b' => $unsortedGroups['b'],
            'c' => $unsortedGroups['c'],
            ''  => $unsortedGroups[''],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysMixed()
    {
        $sorter = new KeysAtEndSorter(['b', 2, '']);

        $unsortedGroups = [
            'a' => new StatementEntityGroup('I'),
            'b' => new StatementEntityGroup('B'),
            2   => new StatementEntityGroup('K'),
            ''  => new StatementEntityGroup('H'),
            'e' => new StatementEntityGroup('E'),
        ];

        $expectedGroups = [
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
            'b' => $unsortedGroups['b'],
            2   => $unsortedGroups[2],
            ''  => $unsortedGroups[''],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysNumbersUnsorted()
    {
        $sorter = new KeysAtEndSorter([3, 1, 2]);

        $unsortedGroups = $this->getUnsortedGroupsNumbered();

        $expectedGroups = [
            0 => $unsortedGroups[0],
            4 => $unsortedGroups[4],
            3 => $unsortedGroups[3],
            1 => $unsortedGroups[1],
            2 => $unsortedGroups[2],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysStringsUnsorted()
    {
        $sorter = new KeysAtEndSorter(['', 'b', 'c']);

        $unsortedGroups = $this->getUnsortedGroupsKeys();

        $expectedGroups = [
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
            ''  => $unsortedGroups[''],
            'b' => $unsortedGroups['b'],
            'c' => $unsortedGroups['c'],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysMixedUnsorted()
    {
        $sorter = new KeysAtEndSorter(['', 'b', 2]);

        $unsortedGroups = $this->getUnsortedGroupsMixed();

        $expectedGroups = [
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
            ''  => $unsortedGroups[''],
            'b' => $unsortedGroups['b'],
            2   => $unsortedGroups[2],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    public function testSortArrayValidKeysEmpty()
    {
        $sorter = new KeysAtEndSorter(['', 'b', 2]);

        $sortedGroups = $sorter->sortArray([]);
        static::assertSame([], $sortedGroups);
    }

    /**
     * Makes a difference for isset/array_key_exists.
     */
    public function testSortArrayValidKeysNullvalues()
    {
        $sorter = new KeysAtEndSorter(['', 'b', 2]);

        $unsortedGroups = [
            'a' => null,
            'b' => null,
            2   => null,
            ''  => null,
            'e' => null,
        ];

        $expectedGroups = [
            'a' => $unsortedGroups['a'],
            'e' => $unsortedGroups['e'],
            ''  => $unsortedGroups[''],
            'b' => $unsortedGroups['b'],
            2   => $unsortedGroups[2],
        ];

        $sortedGroups = $sorter->sortArray($unsortedGroups);
        static::assertSame($expectedGroups, $sortedGroups);
    }

    private function getUnsortedGroupsNumbered(): array
    {
        return [
            new StatementEntityGroup('I'),
            new StatementEntityGroup('B'),
            new StatementEntityGroup('K'),
            new StatementEntityGroup('H'),
            new StatementEntityGroup('E'),
        ];
    }

    private function getUnsortedGroupsKeys(): array
    {
        return [
            'a' => new StatementEntityGroup('I'),
            'b' => new StatementEntityGroup('B'),
            'c' => new StatementEntityGroup('K'),
            ''  => new StatementEntityGroup('H'),
            'e' => new StatementEntityGroup('E'),
        ];
    }

    private function getUnsortedGroupsMixed(): array
    {
        return [
            'a' => new StatementEntityGroup('I'),
            'b' => new StatementEntityGroup('B'),
            2   => new StatementEntityGroup('K'),
            ''  => new StatementEntityGroup('H'),
            'e' => new StatementEntityGroup('E'),
        ];
    }
}
